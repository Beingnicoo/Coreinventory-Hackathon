<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '', 2)), '/');
if ($base === '.' || $base === '') $base = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'CoreInventory' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= htmlspecialchars($base) ?>/assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="d-flex">
    <button class="btn btn-primary sidebar-toggle" id="sidebarToggle" type="button" aria-label="Menu"><i class="bi bi-list"></i></button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <nav class="sidebar col-md-2 col-lg-2 d-md-block p-3 text-white" id="sidebar">
        <div class="position-sticky pt-3">
            <h5 class="px-3 mb-4"><i class="bi bi-box-seam"></i> CoreInventory</h5>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= htmlspecialchars($base) ?>/dashboard/dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                        <?php
                        require_once __DIR__ . '/../config/database.php';
                        $lowCount = 0;
                        if ($conn->query("SHOW TABLES LIKE 'products'")->num_rows) {
                            $r = $conn->query("SELECT COUNT(*) as c FROM products WHERE stock < 10 AND stock > 0");
                            if ($r) $lowCount = $r->fetch_assoc()['c'];
                        }
                        if ($lowCount > 0): ?>
                            <span class="badge bg-danger badge-low ms-1"><?= $lowCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'products' ? 'active' : '' ?>" href="<?= htmlspecialchars($base) ?>/products/product_list.php">
                        <i class="bi bi-box"></i> Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'receipts' ? 'active' : '' ?>" href="<?= htmlspecialchars($base) ?>/operations/receipts.php">
                        <i class="bi bi-box-arrow-in-down"></i> Receipts
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'delivery' ? 'active' : '' ?>" href="<?= htmlspecialchars($base) ?>/operations/delivery.php">
                        <i class="bi bi-box-arrow-up"></i> Delivery
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'transfers' ? 'active' : '' ?>" href="<?= htmlspecialchars($base) ?>/operations/transfers.php">
                        <i class="bi bi-arrow-left-right"></i> Transfers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'adjustments' ? 'active' : '' ?>" href="<?= htmlspecialchars($base) ?>/operations/adjustments.php">
                        <i class="bi bi-sliders"></i> Adjustments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'warehouse' ? 'active' : '' ?>" href="<?= htmlspecialchars($base) ?>/warehouse/warehouse_list.php">
                        <i class="bi bi-building"></i> Warehouses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'ledger' ? 'active' : '' ?>" href="<?= htmlspecialchars($base) ?>/operations/stock_ledger.php">
                        <i class="bi bi-journal-text"></i> Stock Ledger
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <small class="px-3 text-white-50 text-uppercase">Master Data</small>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'suppliers' ? 'active' : '' ?>" href="<?= htmlspecialchars($base) ?>/suppliers/supplier_list.php">
                        <i class="bi bi-truck"></i> Suppliers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'customers' ? 'active' : '' ?>" href="<?= htmlspecialchars($base) ?>/customers/customer_list.php">
                        <i class="bi bi-people"></i> Customers
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <small class="px-3 text-white-50 text-uppercase">Orders</small>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'purchase_orders' ? 'active' : '' ?>" href="<?= htmlspecialchars($base) ?>/purchase_orders/po_list.php">
                        <i class="bi bi-cart-plus"></i> Purchase Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'sales_orders' ? 'active' : '' ?>" href="<?= htmlspecialchars($base) ?>/sales_orders/so_list.php">
                        <i class="bi bi-cart-check"></i> Sales Orders
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <small class="px-3 text-white-50 text-uppercase">Settings</small>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'reorder_points' ? 'active' : '' ?>" href="<?= htmlspecialchars($base) ?>/reorder_points/index.php">
                        <i class="bi bi-exclamation-triangle"></i> Reorder Points
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <small class="px-3 text-white-50 text-uppercase">Reports</small>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'reports' ? 'active' : '' ?>" href="<?= htmlspecialchars($base) ?>/reports/index.php">
                        <i class="bi bi-graph-up"></i> Reports & Analytics
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <button class="nav-link w-100 text-start border-0 bg-transparent" id="darkModeToggle" type="button" style="cursor: pointer;">
                        <i class="bi bi-moon-stars"></i> <span id="darkModeText">Dark Mode</span>
                    </button>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= htmlspecialchars($base) ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Logout</a>
                </li>
            </ul>
        </div>
    </nav>
    <main class="flex-grow-1 p-4 overflow-auto main-content">
