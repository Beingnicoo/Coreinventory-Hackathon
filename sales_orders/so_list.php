<?php
/**
 * Sales Orders List Page
 */
$pageTitle = 'Sales Orders - CoreInventory';
$currentPage = 'sales_orders';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT so.*, c.name as customer_name, u.username as created_by_name 
        FROM sales_orders so 
        LEFT JOIN customers c ON c.id = so.customer_id 
        LEFT JOIN users u ON u.id = so.created_by 
        WHERE 1=1";
$params = [];
$types = "";

if ($statusFilter !== '') {
    $sql .= " AND so.status = ?";
    $params[] = $statusFilter;
    $types = "s";
}

if ($search !== '') {
    $sql .= " AND (so.so_number LIKE ? OR c.name LIKE ?)";
    $s = "%$search%";
    $params[] = $s;
    $params[] = $s;
    $types .= "ss";
}

$sql .= " ORDER BY so.created_at DESC";

// Check if table exists before querying
$orders = [];
if ($conn->query("SHOW TABLES LIKE 'sales_orders'")->num_rows > 0) {
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

foreach ($orders as &$order) {
    $itemStmt = $conn->prepare("SELECT COUNT(*) as cnt, SUM(quantity * unit_price) as total FROM sales_order_items WHERE so_id = ?");
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
    Sales Order <?= $_GET['success'] === 'created' ? 'created' : 'updated' ?> successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <h2><i class="bi bi-cart-check text-primary"></i> Sales Orders</h2>
    <a href="<?= htmlspecialchars($base) ?>/sales_orders/create_so.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Create SO
    </a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by SO number or customer" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Search</button>
            </div>
            <div class="col-md-2">
                <a href="<?= htmlspecialchars($base) ?>/sales_orders/so_list.php" class="btn btn-outline-secondary w-100">Reset</a>
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
                        <th>SO Number</th>
                        <th>Customer</th>
                        <th>Order Date</th>
                        <th>Delivery Date</th>
                        <th>Items</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No sales orders found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($orders as $so): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($so['so_number']) ?></strong></td>
                        <td><?= htmlspecialchars($so['customer_name'] ?: '-') ?></td>
                        <td><?= date('M d, Y', strtotime($so['order_date'])) ?></td>
                        <td><?= $so['delivery_date'] ? date('M d, Y', strtotime($so['delivery_date'])) : '-' ?></td>
                        <td><?= $so['item_count'] ?> item(s)</td>
                        <td>₹<?= number_format($so['total'], 2) ?></td>
                        <td>
                            <?php
                            $badgeClass = ['draft' => 'secondary', 'confirmed' => 'primary', 'delivered' => 'success', 'cancelled' => 'danger'];
                            $status = $so['status'];
                            ?>
                            <span class="badge bg-<?= $badgeClass[$status] ?? 'secondary' ?>"><?= ucfirst($status) ?></span>
                        </td>
                        <td>
                            <a href="<?= htmlspecialchars($base) ?>/sales_orders/view_so.php?id=<?= $so['id'] ?>" class="btn btn-sm btn-outline-info" title="View"><i class="bi bi-eye"></i></a>
                            <?php if ($so['status'] === 'draft'): ?>
                            <a href="<?= htmlspecialchars($base) ?>/sales_orders/edit_so.php?id=<?= $so['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
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

