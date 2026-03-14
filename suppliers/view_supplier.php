<?php
/**
 * View Supplier Details
 * Shows supplier information and related purchase orders
 */
$pageTitle = 'View Supplier - CoreInventory';
$currentPage = 'suppliers';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: supplier_list.php');
    exit;
}

// Check if suppliers table exists
if ($conn->query("SHOW TABLES LIKE 'suppliers'")->num_rows == 0) {
    header('Location: supplier_list.php');
    exit;
}

// Get supplier data
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
if (!$stmt) {
    header('Location: supplier_list.php');
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();

if (!$supplier) {
    header('Location: supplier_list.php');
    exit;
}

// Get purchase orders for this supplier
$purchaseOrders = [];
if ($conn->query("SHOW TABLES LIKE 'purchase_orders'")->num_rows > 0) {
    $poStmt = $conn->prepare("
        SELECT po.*, COUNT(poi.id) as item_count, SUM(poi.quantity * poi.unit_price) as total
        FROM purchase_orders po
        LEFT JOIN purchase_order_items poi ON poi.po_id = po.id
        WHERE po.supplier_id = ?
        GROUP BY po.id
        ORDER BY po.created_at DESC
        LIMIT 10
    ");
    if ($poStmt) {
        $poStmt->bind_param("i", $id);
        if ($poStmt->execute()) {
            $result = $poStmt->get_result();
            $purchaseOrders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }
    }
}

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-truck text-primary"></i> Supplier Details</h2>
    <div class="d-flex gap-2">
        <a href="<?= htmlspecialchars($base) ?>/suppliers/edit_supplier.php?id=<?= $id ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Edit
        </a>
        <a href="<?= htmlspecialchars($base) ?>/suppliers/supplier_list.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Supplier Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="40%">Name:</th>
                        <td><?= htmlspecialchars($supplier['name']) ?></td>
                    </tr>
                    <tr>
                        <th>Code:</th>
                        <td><code><?= htmlspecialchars($supplier['code'] ?: '-') ?></code></td>
                    </tr>
                    <tr>
                        <th>Contact Person:</th>
                        <td><?= htmlspecialchars($supplier['contact_person'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?= htmlspecialchars($supplier['email'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <th>Phone:</th>
                        <td><?= htmlspecialchars($supplier['phone'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge bg-<?= $supplier['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($supplier['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Address:</th>
                        <td><?= nl2br(htmlspecialchars($supplier['address'] ?: '-')) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Recent Purchase Orders</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>PO Number</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($purchaseOrders)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No purchase orders yet.</td></tr>
                            <?php else: ?>
                            <?php foreach ($purchaseOrders as $po): ?>
                            <tr>
                                <td><a href="<?= htmlspecialchars($base) ?>/purchase_orders/view_po.php?id=<?= $po['id'] ?>"><?= htmlspecialchars($po['po_number']) ?></a></td>
                                <td><?= date('M d, Y', strtotime($po['order_date'])) ?></td>
                                <td><span class="badge bg-info"><?= ucfirst($po['status']) ?></span></td>
                                <td>₹<?= number_format($po['total'] ?? 0, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

