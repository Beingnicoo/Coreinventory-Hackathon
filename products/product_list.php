<?php
$pageTitle = 'Products - CoreInventory';
$currentPage = 'products';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$filter = $_GET['filter'] ?? '';
$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM products WHERE 1=1";
$params = [];
$types = "";

if ($filter === 'low') {
    $sql .= " AND stock < 10 AND stock > 0";
}
if ($search !== '') {
    $sql .= " AND (name LIKE ? OR sku LIKE ? OR category LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s];
    $types = "sss";
}

$sql .= " ORDER BY name ASC";
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show">Product added successfully. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?>
<div class="alert alert-success alert-dismissible fade show">Product updated successfully. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <h2><i class="bi bi-box text-primary"></i> Product List</h2>
    <div class="d-flex gap-2">
        <a href="<?= htmlspecialchars($base) ?>/products/add_product.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Add Product
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search by name, SKU, or category" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="filter" class="form-select">
                    <option value="">All Products</option>
                    <option value="low" <?= $filter === 'low' ? 'selected' : '' ?>>Low Stock (&lt;10)</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Search</button>
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
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No products found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($products as $p): ?>
                    <tr class="<?= ($p['stock'] < 10 && $p['stock'] > 0) ? 'table-warning' : '' ?>">
                        <td><?= $p['id'] ?></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><code><?= htmlspecialchars($p['sku']) ?></code></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <svg id="barcode-<?= $p['id'] ?>" class="barcode"></svg>
                                <button class="btn btn-sm btn-outline-secondary" onclick="downloadBarcode('<?= htmlspecialchars($p['sku']) ?>', '<?= htmlspecialchars($p['name']) ?>')" title="Download Barcode">
                                    <i class="bi bi-download"></i>
                                </button>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($p['category']) ?></td>
                        <td><?= htmlspecialchars($p['unit']) ?></td>
                        <td>
                            <?php if ($p['stock'] < 10 && $p['stock'] > 0): ?>
                                <span class="badge bg-danger"><?= $p['stock'] ?> <small>(Low Stock)</small></span>
                            <?php elseif ($p['stock'] == 0): ?>
                                <span class="badge bg-secondary"><?= $p['stock'] ?></span>
                            <?php else: ?>
                                <span class="badge bg-success"><?= $p['stock'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= htmlspecialchars($base) ?>/products/view_product.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info" title="View"><i class="bi bi-eye"></i></a>
                            <a href="<?= htmlspecialchars($base) ?>/operations/receipts.php?product=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success" title="Receive"><i class="bi bi-box-arrow-in-down"></i></a>
                            <a href="<?= htmlspecialchars($base) ?>/operations/delivery.php?product=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info" title="Deliver"><i class="bi bi-box-arrow-up"></i></a>
                            <a href="<?= htmlspecialchars($base) ?>/products/edit_product.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                            <a href="<?= htmlspecialchars($base) ?>/products/delete_product.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this product?');"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- JsBarcode Library -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script>
// Generate barcodes for all products
<?php foreach ($products as $p): ?>
JsBarcode("#barcode-<?= $p['id'] ?>", "<?= htmlspecialchars($p['sku']) ?>", {
    format: "CODE128",
    width: 2,
    height: 40,
    displayValue: true,
    fontSize: 12
});
<?php endforeach; ?>

// Download barcode as image
function downloadBarcode(sku, productName) {
    const canvas = document.createElement('canvas');
    JsBarcode(canvas, sku, {
        format: "CODE128",
        width: 2,
        height: 60,
        displayValue: true,
        fontSize: 14
    });
    
    canvas.toBlob(function(blob) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'Barcode_' + productName.replace(/[^a-z0-9]/gi, '_') + '_' + sku + '.png';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
