<?php
$pageTitle = 'Dashboard - CoreInventory';
$currentPage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$totalProducts = $conn->query("SELECT COUNT(*) as c FROM products")->fetch_assoc()['c'] ?? 0;

// Determine low stock items based on 90% of configured stock level
// Low stock = current stock is <= 10% of configured "full" stock level,
// OR, if no full level is configured, fallback to stock < 10 units.
$hasReorderPoints = $conn->query("SHOW TABLES LIKE 'reorder_points'")->num_rows > 0;

if ($hasReorderPoints) {
    // Use max_stock from reorder_points when available
    $lowStockQuery = $conn->query("
        SELECT p.id, p.name, p.sku, p.category, p.stock, p.unit,
               COALESCE(rp.max_stock, 0) as max_stock
        FROM products p
        LEFT JOIN reorder_points rp ON rp.product_id = p.id AND rp.warehouse_id IS NULL
        WHERE p.stock >= 0
          AND (
                (COALESCE(rp.max_stock, 0) > 0 AND p.stock <= (0.1 * COALESCE(rp.max_stock, 0)))
             OR (COALESCE(rp.max_stock, 0) <= 0 AND p.stock < 10)
          )
        ORDER BY p.stock ASC
    ");
} else {
    // Fallback: use product's reorder_point as reference level when > 0, else stock < 10
    $lowStockQuery = $conn->query("
        SELECT id, name, sku, category, stock, unit,
               COALESCE(reorder_point, 0) as max_stock
        FROM products
        WHERE stock >= 0
          AND (
                (COALESCE(reorder_point, 0) > 0 AND stock <= (0.1 * COALESCE(reorder_point, 0)))
             OR (COALESCE(reorder_point, 0) <= 0 AND stock < 10)
          )
        ORDER BY stock ASC
    ");
}

$lowStockList = $lowStockQuery ? $lowStockQuery->fetch_all(MYSQLI_ASSOC) : [];
$lowStock = count($lowStockList);

$pendingReceipts = $conn->query("SELECT COUNT(*) as c FROM receipts WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'] ?? 0;
$pendingDeliveries = $conn->query("SELECT COUNT(*) as c FROM deliveries WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'] ?? 0;
$transfers = $conn->query("SELECT COUNT(*) as c FROM transfers WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'] ?? 0;

$stockMovement = $conn->query("
    SELECT DATE(created_at) as dt, transaction_type, SUM(quantity) as qty 
    FROM stock_ledger 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at), transaction_type
")->fetch_all(MYSQLI_ASSOC);

$categories = $conn->query("
    SELECT category, COUNT(*) as cnt, SUM(stock) as total_stock 
    FROM products 
    GROUP BY category
")->fetch_all(MYSQLI_ASSOC);

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-speedometer2 text-primary"></i> Dashboard</h2>
    <span class="text-muted">Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
</div>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-muted text-uppercase small">Total Products</h6>
                        <h3 class="mb-0"><?= $totalProducts ?></h3>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                        <i class="bi bi-box text-primary fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-muted text-uppercase small">Low Stock Items</h6>
                        <h3 class="mb-0"><?= $lowStock ?>
                            <?php if ($lowStock > 0): ?>
                                <span class="badge bg-danger ms-1">Alert</span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                        <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-muted text-uppercase small">Pending Receipts</h6>
                        <h3 class="mb-0"><?= $pendingReceipts ?></h3>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i class="bi bi-box-arrow-in-down text-success fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-muted text-uppercase small">Pending Deliveries</h6>
                        <h3 class="mb-0"><?= $pendingDeliveries ?></h3>
                    </div>
                    <div class="rounded-circle bg-info bg-opacity-10 p-3">
                        <i class="bi bi-box-arrow-up text-info fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-muted text-uppercase small">Internal Transfers</h6>
                        <h3 class="mb-0"><?= $transfers ?></h3>
                    </div>
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                        <i class="bi bi-arrow-left-right text-warning fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Stock Movement (Last 7 Days)</h5>
            </div>
            <div class="card-body">
                <canvas id="stockMovementChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Product Categories</h5>
            </div>
            <div class="card-body">
                <canvas id="categoryChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($lowStockList)): ?>
<div class="card border-0 shadow-sm border-danger">
    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-white"><i class="bi bi-exclamation-triangle me-2"></i>Low Stock Alert (<?= count($lowStockList) ?> items)</h5>
        <a href="<?= htmlspecialchars($base) ?>/products/product_list.php?filter=low" class="btn btn-sm btn-light">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-danger">
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStockList as $p): ?>
                    <tr class="table-warning">
                        <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                        <td><code><?= htmlspecialchars($p['sku']) ?></code></td>
                        <td><?= htmlspecialchars($p['category']) ?></td>
                        <td><span class="badge bg-danger"><?= $p['stock'] ?> <?= htmlspecialchars($p['unit']) ?></span></td>
                        <td><span class="badge bg-warning text-dark">Critical</span></td>
                        <td>
                            <a href="<?= htmlspecialchars($base) ?>/operations/receipts.php?product=<?= $p['id'] ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus"></i> Receive Stock
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm border-success">
    <div class="card-body text-center py-4">
        <i class="bi bi-check-circle text-success fs-1"></i>
        <h5 class="mt-2 text-success">All products are well stocked!</h5>
        <p class="text-muted mb-0">No low stock alerts at this time.</p>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($lowStockList)): ?>
<div class="modal fade" id="lowStockModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Low Stock Alert</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>The following items are below 10% of their configured stock level:</p>
                <ul class="mb-0">
                    <?php foreach ($lowStockList as $p): ?>
                    <li><strong><?= htmlspecialchars($p['name']) ?></strong> (<?= htmlspecialchars($p['sku']) ?>) – current stock: <?= (int)$p['stock'] ?> <?= htmlspecialchars($p['unit']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const movementData = <?= json_encode($stockMovement) ?>;
    const byDate = {};
    movementData.forEach(m => {
        if (!byDate[m.dt]) byDate[m.dt] = { receipt: 0, delivery: 0, transfer: 0, adjustment: 0 };
        byDate[m.dt][m.transaction_type] = parseInt(m.qty);
    });
    const dates = Object.keys(byDate).sort();
    new Chart(document.getElementById('stockMovementChart'), {
        type: 'bar',
        data: {
            labels: dates,
            datasets: [
                { label: 'Receipts', data: dates.map(d => byDate[d]?.receipt || 0), backgroundColor: 'rgba(25,135,84,0.7)' },
                { label: 'Deliveries', data: dates.map(d => byDate[d]?.delivery || 0), backgroundColor: 'rgba(13,110,253,0.7)' },
                { label: 'Transfers', data: dates.map(d => byDate[d]?.transfer || 0), backgroundColor: 'rgba(255,193,7,0.7)' },
                { label: 'Adjustments', data: dates.map(d => byDate[d]?.adjustment || 0), backgroundColor: 'rgba(108,117,125,0.7)' }
            ]
        },
        options: { responsive: true, scales: { x: { stacked: true }, y: { stacked: true } } }
    });

    const catData = <?= json_encode($categories) ?>;
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: catData.map(c => c.category),
            datasets: [{ data: catData.map(c => c.cnt), backgroundColor: ['#0d6efd','#198754','#ffc107','#dc3545','#6f42c1','#0dcaf0'] }]
        },
        options: { responsive: true }
    });

    <?php if (!empty($lowStockList)): ?>
    var lowStockModalEl = document.getElementById('lowStockModal');
    if (lowStockModalEl) {
        var lowStockModal = new bootstrap.Modal(lowStockModalEl);
        lowStockModal.show();
    }
    <?php endif; ?>
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
