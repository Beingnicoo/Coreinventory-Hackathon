<?php
$pageTitle = 'Stock Ledger - CoreInventory';
$currentPage = 'ledger';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$search = trim($_GET['search'] ?? '');
$typeFilter = $_GET['type'] ?? '';

$sql = "
    SELECT sl.*, p.name as product_name, p.sku 
    FROM stock_ledger sl 
    JOIN products p ON p.id = sl.product_id 
    WHERE 1=1
";
$params = [];
$types = "";

if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR sl.reference LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s];
    $types = "sss";
}
if ($typeFilter) {
    $sql .= " AND sl.transaction_type = ?";
    $params[] = $typeFilter;
    $types .= "s";
}

$sql .= " ORDER BY sl.created_at DESC LIMIT 100";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$ledger = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-journal-text text-primary"></i> Stock Ledger</h2>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search product or reference" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    <option value="receipt" <?= $typeFilter === 'receipt' ? 'selected' : '' ?>>Receipt</option>
                    <option value="delivery" <?= $typeFilter === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                    <option value="transfer" <?= $typeFilter === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                    <option value="adjustment" <?= $typeFilter === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filter</button>
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
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ledger)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No ledger entries.</td></tr>
                    <?php else: ?>
                    <?php foreach ($ledger as $r): ?>
                    <tr>
                        <td><?= date('M d, Y H:i', strtotime($r['created_at'])) ?></td>
                        <td><code><?= htmlspecialchars($r['reference'] ?? '-') ?></code></td>
                        <td><?= htmlspecialchars($r['product_name']) ?> <small class="text-muted">(<?= $r['sku'] ?>)</small></td>
                        <td>
                            <?php
                            $badges = ['receipt'=>'success','delivery'=>'info','transfer'=>'warning','adjustment'=>'secondary'];
                            $b = $badges[$r['transaction_type']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $b ?>"><?= ucfirst($r['transaction_type']) ?></span>
                        </td>
                        <td>
                            <?php $q = (int)$r['quantity']; ?>
                            <span class="<?= $q >= 0 ? 'text-success' : 'text-danger' ?>"><?= $q >= 0 ? '+' : '' ?><?= $q ?></span>
                        </td>
                        <td><?= htmlspecialchars($r['notes'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
