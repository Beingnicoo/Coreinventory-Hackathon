<?php
/**
 * View Customer Details
 * Shows customer information and related sales orders
 */
$pageTitle = 'View Customer - CoreInventory';
$currentPage = 'customers';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: customer_list.php');
    exit;
}

// Check if customers table exists
if ($conn->query("SHOW TABLES LIKE 'customers'")->num_rows == 0) {
    header('Location: customer_list.php');
    exit;
}

// Get customer data
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
if (!$stmt) {
    header('Location: customer_list.php');
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    header('Location: customer_list.php');
    exit;
}

// Get sales orders for this customer
$salesOrders = [];
if ($conn->query("SHOW TABLES LIKE 'sales_orders'")->num_rows > 0) {
    $soStmt = $conn->prepare("
        SELECT so.*, COUNT(soi.id) as item_count, SUM(soi.quantity * soi.unit_price) as total
        FROM sales_orders so
        LEFT JOIN sales_order_items soi ON soi.so_id = so.id
        WHERE so.customer_id = ?
        GROUP BY so.id
        ORDER BY so.created_at DESC
        LIMIT 10
    ");
    if ($soStmt) {
        $soStmt->bind_param("i", $id);
        if ($soStmt->execute()) {
            $result = $soStmt->get_result();
            $salesOrders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }
    }
}

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people text-primary"></i> Customer Details</h2>
    <div class="d-flex gap-2">
        <a href="<?= htmlspecialchars($base) ?>/customers/edit_customer.php?id=<?= $id ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Edit
        </a>
        <a href="<?= htmlspecialchars($base) ?>/customers/customer_list.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Customer Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="40%">Name:</th>
                        <td><?= htmlspecialchars($customer['name']) ?></td>
                    </tr>
                    <tr>
                        <th>Code:</th>
                        <td><code><?= htmlspecialchars($customer['code'] ?: '-') ?></code></td>
                    </tr>
                    <tr>
                        <th>Contact Person:</th>
                        <td><?= htmlspecialchars($customer['contact_person'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?= htmlspecialchars($customer['email'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <th>Phone:</th>
                        <td><?= htmlspecialchars($customer['phone'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge bg-<?= $customer['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($customer['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Address:</th>
                        <td><?= nl2br(htmlspecialchars($customer['address'] ?: '-')) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Recent Sales Orders</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>SO Number</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($salesOrders)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No sales orders yet.</td></tr>
                            <?php else: ?>
                            <?php foreach ($salesOrders as $so): ?>
                            <tr>
                                <td><a href="<?= htmlspecialchars($base) ?>/sales_orders/view_so.php?id=<?= $so['id'] ?>"><?= htmlspecialchars($so['so_number']) ?></a></td>
                                <td><?= date('M d, Y', strtotime($so['order_date'])) ?></td>
                                <td><span class="badge bg-info"><?= ucfirst($so['status']) ?></span></td>
                                <td>₹<?= number_format($so['total'] ?? 0, 2) ?></td>
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

