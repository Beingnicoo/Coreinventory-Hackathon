<?php
/**
 * Reorder Points Management
 * Manage minimum stock levels and reorder points for products
 */
$pageTitle = 'Reorder Points - CoreInventory';
$currentPage = 'reorder_points';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_reorder'])) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $min_stock = (int)($_POST['min_stock'] ?? 10);
    $max_stock = (int)($_POST['max_stock'] ?? 100);
    $reorder_quantity = (int)($_POST['reorder_quantity'] ?? 50);
    $warehouse_id = !empty($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($product_id && $min_stock >= 0 && $max_stock > $min_stock && $reorder_quantity > 0) {
        // Check if reorder point exists
        $checkStmt = $conn->prepare("SELECT id FROM reorder_points WHERE product_id = ? AND (warehouse_id = ? OR (warehouse_id IS NULL AND ? IS NULL))");
        $checkStmt->bind_param("iii", $product_id, $warehouse_id, $warehouse_id);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();

        if ($existing) {
            // Update existing
            $stmt = $conn->prepare("UPDATE reorder_points SET min_stock = ?, max_stock = ?, reorder_quantity = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param("iiiii", $min_stock, $max_stock, $reorder_quantity, $is_active, $existing['id']);
        } else {
            // Insert new
            $stmt = $conn->prepare("INSERT INTO reorder_points (product_id, warehouse_id, min_stock, max_stock, reorder_quantity, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiiii", $product_id, $warehouse_id, $min_stock, $max_stock, $reorder_quantity, $is_active);
        }

        if ($stmt->execute()) {
            // Also update product's reorder_point field
            $updateStmt = $conn->prepare("UPDATE products SET reorder_point = ? WHERE id = ?");
            $updateStmt->bind_param("ii", $min_stock, $product_id);
            $updateStmt->execute();

            $success = 'Reorder point saved successfully.';
        } else {
            $error = 'Error saving reorder point: ' . $conn->error;
        }
    } else {
        $error = 'Please fill all required fields correctly.';
    }
}

// Check if tables exist
$hasReorderPoints = $conn->query("SHOW TABLES LIKE 'reorder_points'")->num_rows > 0;
$hasProducts = $conn->query("SHOW TABLES LIKE 'products'")->num_rows > 0;
$hasWarehouses = $conn->query("SHOW TABLES LIKE 'warehouses'")->num_rows > 0;

// Get products with current stock and reorder points
$products = [];
if ($hasProducts) {
    if ($hasReorderPoints) {
        $result = $conn->query("
            SELECT p.*, 
                   COALESCE(rp.min_stock, p.reorder_point, 10) as min_stock,
                   COALESCE(rp.max_stock, 100) as max_stock,
                   COALESCE(rp.reorder_quantity, 50) as reorder_quantity,
                   COALESCE(rp.is_active, 1) as is_active,
                   rp.id as rp_id,
                   CASE WHEN p.stock < COALESCE(rp.min_stock, p.reorder_point, 10) THEN 1 ELSE 0 END as needs_reorder
            FROM products p
            LEFT JOIN reorder_points rp ON rp.product_id = p.id AND rp.warehouse_id IS NULL
            ORDER BY needs_reorder DESC, p.name ASC
        ");
        if ($result) {
            $products = $result->fetch_all(MYSQLI_ASSOC);
        }
    } else {
        // If reorder_points table doesn't exist, just get products
        $result = $conn->query("
            SELECT p.*, 
                   COALESCE(p.reorder_point, 10) as min_stock,
                   100 as max_stock,
                   50 as reorder_quantity,
                   1 as is_active,
                   NULL as rp_id,
                   CASE WHEN p.stock < COALESCE(p.reorder_point, 10) THEN 1 ELSE 0 END as needs_reorder
            FROM products p
            ORDER BY needs_reorder DESC, p.name ASC
        ");
        if ($result) {
            $products = $result->fetch_all(MYSQLI_ASSOC);
        }
    }
}

$warehouses = [];
if ($hasWarehouses) {
    $result = $conn->query("SELECT id, name FROM warehouses ORDER BY name");
    if ($result) {
        $warehouses = $result->fetch_all(MYSQLI_ASSOC);
    }
}

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-exclamation-triangle text-primary"></i> Reorder Points</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReorderModal">
        <i class="bi bi-plus-lg"></i> Set Reorder Point
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th class="text-end">Current Stock</th>
                        <th class="text-end">Min Stock</th>
                        <th class="text-end">Max Stock</th>
                        <th class="text-end">Reorder Qty</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No products found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($products as $p): ?>
                    <tr class="<?= $p['needs_reorder'] ? 'table-warning' : '' ?>">
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><code><?= htmlspecialchars($p['sku']) ?></code></td>
                        <td class="text-end">
                            <span class="badge bg-<?= $p['stock'] < $p['min_stock'] ? 'danger' : ($p['stock'] < ($p['min_stock'] * 1.5) ? 'warning' : 'success') ?>">
                                <?= $p['stock'] ?> <?= htmlspecialchars($p['unit']) ?>
                            </span>
                        </td>
                        <td class="text-end"><?= $p['min_stock'] ?></td>
                        <td class="text-end"><?= $p['max_stock'] ?></td>
                        <td class="text-end"><?= $p['reorder_quantity'] ?></td>
                        <td>
                            <?php if ($p['needs_reorder']): ?>
                            <span class="badge bg-danger">Needs Reorder</span>
                            <?php else: ?>
                            <span class="badge bg-success">OK</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editReorder(<?= $p['id'] ?>, <?= $p['min_stock'] ?>, <?= $p['max_stock'] ?>, <?= $p['reorder_quantity'] ?>, <?= $p['is_active'] ?>)">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="addReorderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Set Reorder Point</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Product <span class="text-danger">*</span></label>
                        <select name="product_id" id="product_id" class="form-select" required>
                            <option value="">Select product...</option>
                            <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['sku'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Warehouse</label>
                        <select name="warehouse_id" class="form-select">
                            <option value="">All Warehouses</option>
                            <?php foreach ($warehouses as $w): ?>
                            <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Min Stock <span class="text-danger">*</span></label>
                            <input type="number" name="min_stock" id="min_stock" class="form-control" min="0" required value="10">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Stock <span class="text-danger">*</span></label>
                            <input type="number" name="max_stock" id="max_stock" class="form-control" min="1" required value="100">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Reorder Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="reorder_quantity" id="reorder_quantity" class="form-control" min="1" required value="50">
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="is_active" class="form-check-input" checked>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_reorder" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editReorder(productId, minStock, maxStock, reorderQty, isActive) {
    document.getElementById('product_id').value = productId;
    document.getElementById('min_stock').value = minStock;
    document.getElementById('max_stock').value = maxStock;
    document.getElementById('reorder_quantity').value = reorderQty;
    document.getElementById('is_active').checked = isActive == 1;
    new bootstrap.Modal(document.getElementById('addReorderModal')).show();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

