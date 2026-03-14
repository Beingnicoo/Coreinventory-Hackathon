<?php
/**
 * Edit Customer Page
 * Form to edit an existing customer
 */
$pageTitle = 'Edit Customer - CoreInventory';
$currentPage = 'customers';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$id = (int)($_GET['id'] ?? 0);
$error = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (empty($name)) {
        $error = 'Customer name is required.';
    } else {
        // Check if code already exists for another customer
        if (!empty($code)) {
            $checkStmt = $conn->prepare("SELECT id FROM customers WHERE code = ? AND id != ?");
            $checkStmt->bind_param("si", $code, $id);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $error = 'Customer code already exists.';
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare("UPDATE customers SET name = ?, code = ?, contact_person = ?, email = ?, phone = ?, address = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssssssi", $name, $code, $contact_person, $email, $phone, $address, $status, $id);
            
            if ($stmt->execute()) {
                header('Location: customer_list.php?success=updated');
                exit;
            } else {
                $error = 'Error updating customer: ' . $conn->error;
            }
        }
    }
}

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-pencil text-primary"></i> Edit Customer</h2>
    <a href="<?= htmlspecialchars($base) ?>/customers/customer_list.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to List
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? $customer['name']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Customer Code</label>
                    <input type="text" name="code" class="form-control" value="<?= htmlspecialchars($_POST['code'] ?? $customer['code']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_person" class="form-control" value="<?= htmlspecialchars($_POST['contact_person'] ?? $customer['contact_person']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? $customer['email']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? $customer['phone']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($_POST['status'] ?? $customer['status']) === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($_POST['status'] ?? $customer['status']) === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($_POST['address'] ?? $customer['address']) ?></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Customer</button>
                <a href="<?= htmlspecialchars($base) ?>/customers/customer_list.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

