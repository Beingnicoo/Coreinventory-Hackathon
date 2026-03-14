<?php
/**
 * View Sales Order
 */
$pageTitle = 'View Sales Order - CoreInventory';
$currentPage = 'sales_orders';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if (!$id) {
    header('Location: so_list.php');
    exit;
}

// Check if sales_orders table exists
if ($conn->query("SHOW TABLES LIKE 'sales_orders'")->num_rows == 0) {
    header('Location: so_list.php');
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, ['draft', 'confirmed', 'delivered', 'cancelled'])) {
        $stmt = $conn->prepare("UPDATE sales_orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $id);
        $stmt->execute();
        
        // If status is 'delivered', create deliveries for all items
        if ($newStatus === 'delivered') {
            $conn->begin_transaction();
            try {
                $itemsStmt = $conn->prepare("SELECT soi.*, p.name as product_name FROM sales_order_items soi JOIN products p ON p.id = soi.product_id WHERE soi.so_id = ?");
                $itemsStmt->bind_param("i", $id);
                $itemsStmt->execute();
                $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                foreach ($items as $item) {
                    // Check stock
                    $stockStmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
                    $stockStmt->bind_param("i", $item['product_id']);
                    $stockStmt->execute();
                    $stock = $stockStmt->get_result()->fetch_assoc()['stock'] ?? 0;
                    
                    if ($stock >= $item['quantity']) {
                        // Create delivery
                        $deliveryStmt = $conn->prepare("INSERT INTO deliveries (product_id, customer_id, so_id, quantity, notes) VALUES (?, (SELECT customer_id FROM sales_orders WHERE id = ?), ?, ?, ?)");
                        $notes = "From SO: " . $id;
                        $deliveryStmt->bind_param("iiis", $item['product_id'], $id, $item['quantity'], $notes);
                        $deliveryStmt->execute();
                        
                        // Update stock
                        $stockUpdateStmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                        $stockUpdateStmt->bind_param("ii", $item['quantity'], $item['product_id']);
                        $stockUpdateStmt->execute();
                        
                        // Update stock ledger
                        $ref = "SO-" . $id;
                        $ledgerStmt = $conn->prepare("INSERT INTO stock_ledger (product_id, transaction_type, quantity, reference, notes) VALUES (?, 'delivery', ?, ?, ?)");
                        $ledgerStmt->bind_param("iiss", $item['product_id'], $item['quantity'], $ref, $notes);
                        $ledgerStmt->execute();
                    } else {
                        throw new Exception("Insufficient stock for product: " . $item['product_name']);
                    }
                }
                
                $conn->commit();
                $success = 'Sales order delivered. Stock updated successfully.';
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Error processing delivery: ' . $e->getMessage();
            }
        } else {
            $success = 'Status updated successfully.';
        }
    }
}

// Get SO data
$stmt = $conn->prepare("SELECT so.*, c.name as customer_name, c.code as customer_code, u.username as created_by_name FROM sales_orders so LEFT JOIN customers c ON c.id = so.customer_id LEFT JOIN users u ON u.id = so.created_by WHERE so.id = ?");
if (!$stmt) {
    header('Location: so_list.php');
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$so = $stmt->get_result()->fetch_assoc();

if (!$so) {
    header('Location: so_list.php');
    exit;
}

// Get items
$items = [];
if ($conn->query("SHOW TABLES LIKE 'sales_order_items'")->num_rows > 0) {
    $itemsStmt = $conn->prepare("SELECT soi.*, p.name as product_name, p.sku FROM sales_order_items soi JOIN products p ON p.id = soi.product_id WHERE soi.so_id = ?");
    if ($itemsStmt) {
        $itemsStmt->bind_param("i", $id);
        if ($itemsStmt->execute()) {
            $result = $itemsStmt->get_result();
            $items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }
    }
}

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">Sales Order created successfully.</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-text text-primary"></i> Sales Order: <?= htmlspecialchars($so['so_number']) ?></h2>
    <a href="<?= htmlspecialchars($base) ?>/sales_orders/so_list.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Order Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr><th width="40%">SO Number:</th><td><strong><?= htmlspecialchars($so['so_number']) ?></strong></td></tr>
                            <tr><th>Customer:</th><td><?= htmlspecialchars($so['customer_name']) ?> (<?= htmlspecialchars($so['customer_code'] ?: '-') ?>)</td></tr>
                            <tr><th>Order Date:</th><td><?= date('M d, Y', strtotime($so['order_date'])) ?></td></tr>
                            <tr><th>Delivery Date:</th><td><?= $so['delivery_date'] ? date('M d, Y', strtotime($so['delivery_date'])) : '-' ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr><th width="40%">Status:</th><td>
                                <?php
                                $badgeClass = ['draft' => 'secondary', 'confirmed' => 'primary', 'delivered' => 'success', 'cancelled' => 'danger'];
                                ?>
                                <span class="badge bg-<?= $badgeClass[$so['status']] ?? 'secondary' ?>"><?= ucfirst($so['status']) ?></span>
                            </td></tr>
                            <tr><th>Total Amount:</th><td><strong>₹<?= number_format($so['total_amount'], 2) ?></strong></td></tr>
                            <tr><th>Created By:</th><td><?= htmlspecialchars($so['created_by_name'] ?: '-') ?></td></tr>
                            <tr><th>Created At:</th><td><?= date('M d, Y H:i', strtotime($so['created_at'])) ?></td></tr>
                        </table>
                    </div>
                </div>
                <?php if ($so['notes']): ?>
                <div class="mt-3">
                    <strong>Notes:</strong><br>
                    <?= nl2br(htmlspecialchars($so['notes'])) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Order Items</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td><code><?= htmlspecialchars($item['sku']) ?></code></td>
                                <td class="text-end"><?= $item['quantity'] ?></td>
                                <td class="text-end">₹<?= number_format($item['unit_price'], 2) ?></td>
                                <td class="text-end"><strong>₹<?= number_format($item['quantity'] * $item['unit_price'], 2) ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end">Grand Total:</th>
                                <th class="text-end">₹<?= number_format($so['total_amount'], 2) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-gear"></i> Actions</h5>
            </div>
            <div class="card-body">
                <?php if ($so['status'] === 'draft'): ?>
                <a href="<?= htmlspecialchars($base) ?>/sales_orders/edit_so.php?id=<?= $id ?>" class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-pencil"></i> Edit Order
                </a>
                <?php endif; ?>
                
                <form method="POST" class="mt-3">
                    <label class="form-label">Update Status:</label>
                    <select name="status" class="form-select mb-2">
                        <option value="draft" <?= $so['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="confirmed" <?= $so['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="delivered" <?= $so['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $so['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                    <button type="submit" name="update_status" class="btn btn-outline-primary w-100">
                        <i class="bi bi-check"></i> Update Status
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

