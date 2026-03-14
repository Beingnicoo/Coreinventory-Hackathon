<?php
/**
 * Reports & Analytics Dashboard
 * Provides comprehensive reporting and analytics
 */
$pageTitle = 'Reports & Analytics - CoreInventory';
$currentPage = 'reports';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$reportType = $_GET['type'] ?? 'overview';

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';

// Helper function to safely execute queries
function safeQuery($conn, $query, $default = []) {
    $result = $conn->query($query);
    if ($result === false) {
        return $default;
    }
    if (is_object($result) && method_exists($result, 'fetch_all')) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    return $default;
}

function safeQuerySingle($conn, $query, $default = 0) {
    $result = $conn->query($query);
    if ($result === false) {
        return $default;
    }
    $row = $result->fetch_assoc();
    return $row ? ($row['c'] ?? $row['total'] ?? $default) : $default;
}

// Check if tables exist
$tablesExist = [
    'products' => $conn->query("SHOW TABLES LIKE 'products'")->num_rows > 0,
    'suppliers' => $conn->query("SHOW TABLES LIKE 'suppliers'")->num_rows > 0,
    'customers' => $conn->query("SHOW TABLES LIKE 'customers'")->num_rows > 0,
    'sales_orders' => $conn->query("SHOW TABLES LIKE 'sales_orders'")->num_rows > 0,
    'purchase_orders' => $conn->query("SHOW TABLES LIKE 'purchase_orders'")->num_rows > 0,
    'stock_ledger' => $conn->query("SHOW TABLES LIKE 'stock_ledger'")->num_rows > 0,
    'deliveries' => $conn->query("SHOW TABLES LIKE 'deliveries'")->num_rows > 0,
    'receipts' => $conn->query("SHOW TABLES LIKE 'receipts'")->num_rows > 0,
];

// Get overview statistics
$totalProducts = $tablesExist['products'] ? safeQuerySingle($conn, "SELECT COUNT(*) as c FROM products", 0) : 0;
$totalSuppliers = ($tablesExist['suppliers'] && $tablesExist['products']) ? safeQuerySingle($conn, "SELECT COUNT(*) as c FROM suppliers WHERE status = 'active'", 0) : 0;
$totalCustomers = ($tablesExist['customers'] && $tablesExist['products']) ? safeQuerySingle($conn, "SELECT COUNT(*) as c FROM customers WHERE status = 'active'", 0) : 0;
$lowStockCount = $tablesExist['products'] ? safeQuerySingle($conn, "SELECT COUNT(*) as c FROM products WHERE stock < 10 AND stock > 0", 0) : 0;

// Sales summary - use sales_orders totals (confirmed or delivered)
$totalSales = 0;
if ($tablesExist['sales_orders']) {
    $salesStmt = $conn->prepare("
        SELECT SUM(total_amount) as total
        FROM sales_orders
        WHERE status IN ('confirmed', 'delivered')
          AND order_date BETWEEN ? AND ?
    ");
    if ($salesStmt) {
        $salesStmt->bind_param("ss", $dateFrom, $dateTo);
        $salesStmt->execute();
        $result = $salesStmt->get_result();
        if ($result) {
            $row = $result->fetch_assoc();
            $totalSales = $row ? ($row['total'] ?? 0) : 0;
        }
    }
}

// Purchase summary - use purchase_orders totals (confirmed or received)
$totalPurchases = 0;
if ($tablesExist['purchase_orders']) {
    $purchaseStmt = $conn->prepare("
        SELECT SUM(total_amount) as total
        FROM purchase_orders
        WHERE status IN ('confirmed', 'received')
          AND order_date BETWEEN ? AND ?
    ");
    if ($purchaseStmt) {
        $purchaseStmt->bind_param("ss", $dateFrom, $dateTo);
        $purchaseStmt->execute();
        $result = $purchaseStmt->get_result();
        if ($result) {
            $row = $result->fetch_assoc();
            $totalPurchases = $row ? ($row['total'] ?? 0) : 0;
        }
    }
}

// Top products
$topProducts = [];
if ($tablesExist['products'] && $tablesExist['stock_ledger']) {
    $topProducts = safeQuery($conn, "
        SELECT p.name, p.sku, COALESCE(SUM(sl.quantity), 0) as total_qty, 
               COALESCE(SUM(CASE WHEN sl.transaction_type = 'receipt' THEN sl.quantity ELSE 0 END), 0) as received,
               COALESCE(SUM(CASE WHEN sl.transaction_type = 'delivery' THEN sl.quantity ELSE 0 END), 0) as delivered
        FROM products p
        LEFT JOIN stock_ledger sl ON sl.product_id = p.id AND DATE(sl.created_at) BETWEEN '$dateFrom' AND '$dateTo'
        GROUP BY p.id, p.name, p.sku
        ORDER BY total_qty DESC
        LIMIT 10
    ", []);
} elseif ($tablesExist['products']) {
    $topProducts = safeQuery($conn, "
        SELECT name, sku, 0 as total_qty, 0 as received, 0 as delivered
        FROM products
        LIMIT 10
    ", []);
}

// Category distribution
$categories = [];
if ($tablesExist['products']) {
    // Check if cost_price column exists
    $hasCostPrice = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'cost_price'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasCostPrice = true;
    }
    
    if ($hasCostPrice) {
        $categories = safeQuery($conn, "
            SELECT category, COUNT(*) as count, SUM(stock) as total_stock, 
                   SUM(COALESCE(cost_price, 0) * stock) as total_value
            FROM products
            GROUP BY category
            ORDER BY count DESC
        ", []);
    } else {
        $categories = safeQuery($conn, "
            SELECT category, COUNT(*) as count, SUM(stock) as total_stock, 
                   0 as total_value
            FROM products
            GROUP BY category
            ORDER BY count DESC
        ", []);
    }
}

// Monthly sales trend - use sales_orders totals (confirmed or delivered)
$monthlySales = [];
if ($tablesExist['sales_orders']) {
    $monthlySales = safeQuery($conn, "
        SELECT DATE_FORMAT(order_date, '%Y-%m') as month,
               COALESCE(SUM(total_amount), 0) as total
        FROM sales_orders
        WHERE status IN ('confirmed', 'delivered')
          AND order_date BETWEEN '$dateFrom' AND '$dateTo'
        GROUP BY DATE_FORMAT(order_date, '%Y-%m')
        ORDER BY month ASC
    ", []);
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-graph-up text-primary"></i> Reports & Analytics</h2>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Report Type</label>
                <select name="type" class="form-select">
                    <option value="overview" <?= $reportType === 'overview' ? 'selected' : '' ?>>Overview</option>
                    <option value="sales" <?= $reportType === 'sales' ? 'selected' : '' ?>>Sales</option>
                    <option value="purchases" <?= $reportType === 'purchases' ? 'selected' : '' ?>>Purchases</option>
                    <option value="inventory" <?= $reportType === 'inventory' ? 'selected' : '' ?>>Inventory</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Generate Report</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Total Products</h6>
                <h3><?= $totalProducts ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Total Sales</h6>
                <h3>₹<?= number_format($totalSales, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Total Purchases</h6>
                <h3>₹<?= number_format($totalPurchases, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Low Stock Items</h6>
                <h3 class="text-danger"><?= $lowStockCount ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Monthly Sales Trend</h5>
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Category Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="categoryChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-2">
    <div class="col-lg-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-list-ol"></i> Top Products by Movement</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th class="text-end">Received</th>
                                <th class="text-end">Delivered</th>
                                <th class="text-end">Net Movement</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProducts as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><code><?= htmlspecialchars($p['sku']) ?></code></td>
                                <td class="text-end text-success">+<?= $p['received'] ?? 0 ?></td>
                                <td class="text-end text-danger">-<?= $p['delivered'] ?? 0 ?></td>
                                <td class="text-end"><strong><?= ($p['received'] ?? 0) - ($p['delivered'] ?? 0) ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const monthlySales = <?= json_encode($monthlySales) ?>;
const categories = <?= json_encode($categories) ?>;

// Sales Trend Chart
new Chart(document.getElementById('salesChart'), {
    type: 'line',
    data: {
        labels: monthlySales.map(s => s.month),
        datasets: [{
            label: 'Sales (₹)',
            data: monthlySales.map(s => parseFloat(s.total)),
            borderColor: 'rgb(13, 110, 253)',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Category Chart
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: categories.map(c => c.category),
        datasets: [{
            data: categories.map(c => c.count),
            backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#0dcaf0', '#fd7e14']
        }]
    },
    options: {
        responsive: true
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

