<?php
/**
 * Edit Supplier Page
 * Form to edit an existing supplier
 */
$pageTitle = 'Edit Supplier - CoreInventory';
$currentPage = 'suppliers';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$id = (int)($_GET['id'] ?? 0);
$error = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (empty($name)) {
        $error = 'Supplier name is required.';
    } else {
        // Check if code already exists for another supplier
        if (!empty($code)) {
            $checkStmt = $conn->prepare("SELECT id FROM suppliers WHERE code = ? AND id != ?");
            $checkStmt->bind_param("si", $code, $id);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $error = 'Supplier code already exists.';
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare("UPDATE suppliers SET name = ?, code = ?, contact_person = ?, email = ?, phone = ?, address = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssssssi", $name, $code, $contact_person, $email, $phone, $address, $status, $id);
            
            if ($stmt->execute()) {
                header('Location: supplier_list.php?success=updated');
                exit;
            } else {
                $error = 'Error updating supplier: ' . $conn->error;
            }
        }
    }
}

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-pencil text-primary"></i> Edit Supplier</h2>
    <a href="<?= htmlspecialchars($base) ?>/suppliers/supplier_list.php" class="btn btn-outline-secondary">
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
                    <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? $supplier['name']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Supplier Code</label>
                    <input type="text" name="code" class="form-control" value="<?= htmlspecialchars($_POST['code'] ?? $supplier['code']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_person" class="form-control" value="<?= htmlspecialchars($_POST['contact_person'] ?? $supplier['contact_person']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? $supplier['email']) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? $supplier['phone']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($_POST['status'] ?? $supplier['status']) === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($_POST['status'] ?? $supplier['status']) === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($_POST['address'] ?? $supplier['address']) ?></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Supplier</button>
                <a href="<?= htmlspecialchars($base) ?>/suppliers/supplier_list.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

