<?php
$pageTitle = 'Edit Product - CoreInventory';
$currentPage = 'products';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: product_list.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header('Location: product_list.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $unit = trim($_POST['unit'] ?? 'pcs');
    $cost_price = floatval($_POST['cost_price'] ?? 0);
    $selling_price = floatval($_POST['selling_price'] ?? 0);
    $reorder_point = (int)($_POST['reorder_point'] ?? 10);
    $barcode = trim($_POST['barcode'] ?? '');

    if (empty($name) || empty($sku) || empty($category)) {
        $error = 'Name, SKU, and Category are required.';
    } else {
        // Auto-generate barcode from SKU if not provided
        if (empty($barcode)) {
            $barcode = $sku;
        }
        
        $stmt = $conn->prepare("UPDATE products SET name=?, sku=?, category=?, unit=?, cost_price=?, selling_price=?, reorder_point=?, barcode=? WHERE id=?");
        $stmt->bind_param("ssssddisi", $name, $sku, $category, $unit, $cost_price, $selling_price, $reorder_point, $barcode, $id);

        if ($stmt->execute()) {
            header("Location: product_list.php?updated=1");
            exit;
        } else {
            $error = 'SKU already exists. Please use a unique SKU.';
        }
    }
    $product = array_merge($product, $_POST);
}

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-pencil text-primary"></i> Edit Product</h2>
    <a href="<?= htmlspecialchars($base) ?>/products/product_list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card border-0 shadow-sm" style="max-width: 600px;">
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($product['name']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">SKU <span class="text-danger">*</span></label>
                <input type="text" name="sku" class="form-control" required value="<?= htmlspecialchars($product['sku']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Category <span class="text-danger">*</span></label>
                <input type="text" name="category" class="form-control" required value="<?= htmlspecialchars($product['category']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Unit</label>
                <select name="unit" class="form-select">
                    <option value="pcs" <?= $product['unit'] === 'pcs' ? 'selected' : '' ?>>pcs</option>
                    <option value="kg" <?= $product['unit'] === 'kg' ? 'selected' : '' ?>>kg</option>
                    <option value="L" <?= $product['unit'] === 'L' ? 'selected' : '' ?>>L</option>
                    <option value="box" <?= $product['unit'] === 'box' ? 'selected' : '' ?>>box</option>
                    <option value="m" <?= $product['unit'] === 'm' ? 'selected' : '' ?>>m</option>
                </select>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Cost Price</label>
                    <input type="number" name="cost_price" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($product['cost_price'] ?? '0') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Selling Price</label>
                    <input type="number" name="selling_price" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($product['selling_price'] ?? '0') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Reorder Point</label>
                    <input type="number" name="reorder_point" class="form-control" min="0" value="<?= (int)($product['reorder_point'] ?? 10) ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Barcode</label>
                <input type="text" name="barcode" class="form-control" placeholder="Auto-generated from SKU if empty" value="<?= htmlspecialchars($product['barcode'] ?? '') ?>">
                <small class="text-muted">Leave empty to auto-generate from SKU</small>
            </div>
            <div class="mb-4 text-muted small">Current stock: <strong><?= $product['stock'] ?></strong> (use Receipts/Delivery/Adjustments to change)</div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Product</button>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
