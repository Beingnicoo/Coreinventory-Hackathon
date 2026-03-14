<?php
/**
 * Add Customer Page
 * Form to add a new customer
 */
$pageTitle = 'Add Customer - CoreInventory';
$currentPage = 'customers';
require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

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
        // Check if code already exists (if provided)
        if (!empty($code)) {
            $checkStmt = $conn->prepare("SELECT id FROM customers WHERE code = ?");
            $checkStmt->bind_param("s", $code);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $error = 'Customer code already exists.';
            }
        }

        if (empty($error)) {
            // Generate code if not provided
            if (empty($code)) {
                $code = 'CUS-' . str_pad(($conn->query("SELECT COUNT(*) as c FROM customers")->fetch_assoc()['c'] ?? 0) + 1, 5, '0', STR_PAD_LEFT);
            }

            $stmt = $conn->prepare("INSERT INTO customers (name, code, contact_person, email, phone, address, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $name, $code, $contact_person, $email, $phone, $address, $status);
            
            if ($stmt->execute()) {
                header('Location: customer_list.php?success=added');
                exit;
            } else {
                $error = 'Error adding customer: ' . $conn->error;
            }
        }
    }
}

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-plus-circle text-primary"></i> Add Customer</h2>
    <a href="<?= htmlspecialchars($base) ?>/customers/customer_list.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to List
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Customer Code</label>
                    <input type="text" name="code" class="form-control" placeholder="Auto-generated if left empty" value="<?= htmlspecialchars($_POST['code'] ?? '') ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_person" class="form-control" value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Customer</button>
                <a href="<?= htmlspecialchars($base) ?>/customers/customer_list.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

