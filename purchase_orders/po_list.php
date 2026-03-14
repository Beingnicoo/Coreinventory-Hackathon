<?php
/**
 * Purchase Orders List Page
 * Displays all purchase orders with filters
 */
$pageTitle = 'Purchase Orders - CoreInventory';
$currentPage = 'purchase_orders';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT po.*, s.name as supplier_name, u.username as created_by_name 
        FROM purchase_orders po 
        LEFT JOIN suppliers s ON s.id = po.supplier_id 
        LEFT JOIN users u ON u.id = po.created_by 
        WHERE 1=1";
$params = [];
$types = "";

if ($statusFilter !== '') {
    $sql .= " AND po.status = ?";
    $params[] = $statusFilter;
    $types = "s";
}

if ($search !== '') {
    $sql .= " AND (po.po_number LIKE ? OR s.name LIKE ?)";
    $s = "%$search%";
    $params[] = $s;
    $params[] = $s;
    $types .= "ss";
}

$sql .= " ORDER BY po.created_at DESC";

// Check if table exists before querying
$orders = [];
if ($conn->query("SHOW TABLES LIKE 'purchase_orders'")->num_rows > 0) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }
    }
}

// Get item counts and totals for each PO
foreach ($orders as &$order) {
    $itemStmt = $conn->prepare("SELECT COUNT(*) as cnt, SUM(quantity * unit_price) as total FROM purchase_order_items WHERE po_id = ?");
    if ($itemStmt) {
        $itemStmt->bind_param("i", $order['id']);
        $itemStmt->execute();
        $itemResult = $itemStmt->get_result();
        if ($itemResult) {
            $itemData = $itemResult->fetch_assoc();
            $order['item_count'] = $itemData['cnt'] ?? 0;
            $order['total'] = $itemData['total'] ?? 0;
        } else {
            $order['item_count'] = 0;
            $order['total'] = 0;
        }
    } else {
        $order['item_count'] = 0;
        $order['total'] = 0;
    }
}

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    Purchase Order <?= $_GET['success'] === 'created' ? 'created' : 'updated' ?> successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <h2><i class="bi bi-cart-plus text-primary"></i> Purchase Orders</h2>
    <a href="<?= htmlspecialchars($base) ?>/purchase_orders/create_po.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Create PO
    </a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by PO number or supplier" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="received" <?= $statusFilter === 'received' ? 'selected' : '' ?>>Received</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Search</button>
            </div>
            <div class="col-md-2">
                <a href="<?= htmlspecialchars($base) ?>/purchase_orders/po_list.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>PO Number</th>
                        <th>Supplier</th>
                        <th>Order Date</th>
                        <th>Expected Date</th>
                        <th>Items</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No purchase orders found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($orders as $po): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($po['po_number']) ?></strong></td>
                        <td><?= htmlspecialchars($po['supplier_name'] ?: '-') ?></td>
                        <td><?= date('M d, Y', strtotime($po['order_date'])) ?></td>
                        <td><?= $po['expected_date'] ? date('M d, Y', strtotime($po['expected_date'])) : '-' ?></td>
                        <td><?= $po['item_count'] ?> item(s)</td>
                        <td>₹<?= number_format($po['total'], 2) ?></td>
                        <td>
                            <?php
                            $badgeClass = [
                                'draft' => 'secondary',
                                'confirmed' => 'primary',
                                'received' => 'success',
                                'cancelled' => 'danger'
                            ];
                            $status = $po['status'];
                            ?>
                            <span class="badge bg-<?= $badgeClass[$status] ?? 'secondary' ?>"><?= ucfirst($status) ?></span>
                        </td>
                        <td>
                            <a href="<?= htmlspecialchars($base) ?>/purchase_orders/view_po.php?id=<?= $po['id'] ?>" class="btn btn-sm btn-outline-info" title="View"><i class="bi bi-eye"></i></a>
                            <?php if ($po['status'] === 'draft'): ?>
                            <a href="<?= htmlspecialchars($base) ?>/purchase_orders/edit_po.php?id=<?= $po['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

