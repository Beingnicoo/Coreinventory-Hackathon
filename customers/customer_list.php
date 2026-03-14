<?php
/**
 * Customer List Page
 * Displays all customers with search and filter functionality
 */
$pageTitle = 'Customers - CoreInventory';
$currentPage = 'customers';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

// Build query with filters
$sql = "SELECT * FROM customers WHERE 1=1";
$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (name LIKE ? OR code LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s, $s];
    $types = "ssss";
}

if ($statusFilter !== '') {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql .= " ORDER BY name ASC";

// Check if table exists before querying
$customers = [];
if ($conn->query("SHOW TABLES LIKE 'customers'")->num_rows > 0) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $customers = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }
    }
}

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    Customer <?= $_GET['success'] === 'added' ? 'added' : 'updated' ?> successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    Customer deleted successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <h2><i class="bi bi-people text-primary"></i> Customers</h2>
    <a href="<?= htmlspecialchars($base) ?>/customers/add_customer.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add Customer
    </a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by name, code, contact, or email" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Search</button>
            </div>
            <div class="col-md-2">
                <a href="<?= htmlspecialchars($base) ?>/customers/customer_list.php" class="btn btn-outline-secondary w-100">Reset</a>
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
                        <th>#</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No customers found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><?= $c['id'] ?></td>
                        <td><code><?= htmlspecialchars($c['code'] ?: '-') ?></code></td>
                        <td><?= htmlspecialchars($c['name']) ?></td>
                        <td><?= htmlspecialchars($c['contact_person'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($c['email'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($c['phone'] ?: '-') ?></td>
                        <td>
                            <span class="badge bg-<?= $c['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($c['status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= htmlspecialchars($base) ?>/customers/view_customer.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-info" title="View"><i class="bi bi-eye"></i></a>
                            <a href="<?= htmlspecialchars($base) ?>/customers/edit_customer.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                            <a href="<?= htmlspecialchars($base) ?>/customers/delete_customer.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this customer?');"><i class="bi bi-trash"></i></a>
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

