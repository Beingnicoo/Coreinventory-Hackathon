<?php
/**
 * View Purchase Order
 * Displays purchase order details and allows status updates
 */
$pageTitle = 'View Purchase Order - CoreInventory';
$currentPage = 'purchase_orders';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if (!$id) {
    header('Location: po_list.php');
    exit;
}

// Check if purchase_orders table exists
if ($conn->query("SHOW TABLES LIKE 'purchase_orders'")->num_rows == 0) {
    header('Location: po_list.php');
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, ['draft', 'confirmed', 'received', 'cancelled'])) {
        $stmt = $conn->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $id);
        $stmt->execute();
        
        // If status is 'received', create receipts for all items
        if ($newStatus === 'received') {
            $conn->begin_transaction();
            try {
                $itemsStmt = $conn->prepare("SELECT poi.*, p.name as product_name FROM purchase_order_items poi JOIN products p ON p.id = poi.product_id WHERE poi.po_id = ?");
                $itemsStmt->bind_param("i", $id);
                $itemsStmt->execute();
                $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                foreach ($items as $item) {
                    // Create receipt
                    $receiptStmt = $conn->prepare("INSERT INTO receipts (product_id, supplier_id, po_id, quantity, notes) VALUES (?, (SELECT supplier_id FROM purchase_orders WHERE id = ?), ?, ?, ?)");
                    $notes = "From PO: " . $id;
                    $receiptStmt->bind_param("iiis", $item['product_id'], $id, $item['quantity'], $notes);
                    $receiptStmt->execute();
                    
                    // Update stock
                    $stockStmt = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                    $stockStmt->bind_param("ii", $item['quantity'], $item['product_id']);
                    $stockStmt->execute();
                    
                    // Update stock ledger
                    $ref = "PO-" . $id;
                    $ledgerStmt = $conn->prepare("INSERT INTO stock_ledger (product_id, transaction_type, quantity, reference, notes) VALUES (?, 'receipt', ?, ?, ?)");
                    $ledgerStmt->bind_param("iiss", $item['product_id'], $item['quantity'], $ref, $notes);
                    $ledgerStmt->execute();
                }
                
                $conn->commit();
                $success = 'Purchase order received. Stock updated successfully.';
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Error processing receipt: ' . $e->getMessage();
            }
        } else {
            $success = 'Status updated successfully.';
        }
    }
}

// Get PO data
$stmt = $conn->prepare("SELECT po.*, s.name as supplier_name, s.code as supplier_code, u.username as created_by_name FROM purchase_orders po LEFT JOIN suppliers s ON s.id = po.supplier_id LEFT JOIN users u ON u.id = po.created_by WHERE po.id = ?");
if (!$stmt) {
    header('Location: po_list.php');
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();

if (!$po) {
    header('Location: po_list.php');
    exit;
}

// Get items
$items = [];
if ($conn->query("SHOW TABLES LIKE 'purchase_order_items'")->num_rows > 0) {
    $itemsStmt = $conn->prepare("SELECT poi.*, p.name as product_name, p.sku FROM purchase_order_items poi JOIN products p ON p.id = poi.product_id WHERE poi.po_id = ?");
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
<div class="alert alert-success">Purchase Order created successfully.</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-text text-primary"></i> Purchase Order: <?= htmlspecialchars($po['po_number']) ?></h2>
    <a href="<?= htmlspecialchars($base) ?>/purchase_orders/po_list.php" class="btn btn-outline-secondary">
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
                            <tr><th width="40%">PO Number:</th><td><strong><?= htmlspecialchars($po['po_number']) ?></strong></td></tr>
                            <tr><th>Supplier:</th><td><?= htmlspecialchars($po['supplier_name']) ?> (<?= htmlspecialchars($po['supplier_code'] ?: '-') ?>)</td></tr>
                            <tr><th>Order Date:</th><td><?= date('M d, Y', strtotime($po['order_date'])) ?></td></tr>
                            <tr><th>Expected Date:</th><td><?= $po['expected_date'] ? date('M d, Y', strtotime($po['expected_date'])) : '-' ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr><th width="40%">Status:</th><td>
                                <?php
                                $badgeClass = ['draft' => 'secondary', 'confirmed' => 'primary', 'received' => 'success', 'cancelled' => 'danger'];
                                ?>
                                <span class="badge bg-<?= $badgeClass[$po['status']] ?? 'secondary' ?>"><?= ucfirst($po['status']) ?></span>
                            </td></tr>
                            <tr><th>Total Amount:</th><td><strong>₹<?= number_format($po['total_amount'], 2) ?></strong></td></tr>
                            <tr><th>Created By:</th><td><?= htmlspecialchars($po['created_by_name'] ?: '-') ?></td></tr>
                            <tr><th>Created At:</th><td><?= date('M d, Y H:i', strtotime($po['created_at'])) ?></td></tr>
                        </table>
                    </div>
                </div>
                <?php if ($po['notes']): ?>
                <div class="mt-3">
                    <strong>Notes:</strong><br>
                    <?= nl2br(htmlspecialchars($po['notes'])) ?>
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
                                <th class="text-end">₹<?= number_format($po['total_amount'], 2) ?></th>
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
                <?php if ($po['status'] === 'draft'): ?>
                <a href="<?= htmlspecialchars($base) ?>/purchase_orders/edit_po.php?id=<?= $id ?>" class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-pencil"></i> Edit Order
                </a>
                <?php endif; ?>
                
                <form method="POST" class="mt-3">
                    <label class="form-label">Update Status:</label>
                    <select name="status" class="form-select mb-2">
                        <option value="draft" <?= $po['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="confirmed" <?= $po['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="received" <?= $po['status'] === 'received' ? 'selected' : '' ?>>Received</option>
                        <option value="cancelled" <?= $po['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
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

