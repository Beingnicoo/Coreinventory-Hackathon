<?php
$pageTitle = 'Internal Transfers - CoreInventory';
$currentPage = 'transfers';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $from_wh = (int)($_POST['from_warehouse_id'] ?? 0) ?: null;
    $to_wh = (int)($_POST['to_warehouse_id'] ?? 0) ?: null;
    $quantity = (int)($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if (!$product_id || $quantity <= 0) {
        $error = 'Please select a product and enter a valid quantity.';
    } else {
        $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        if (!$product || $product['stock'] < $quantity) {
            $error = 'Insufficient stock. Available: ' . ($product['stock'] ?? 0);
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO transfers (product_id, from_warehouse_id, to_warehouse_id, quantity, notes) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiiis", $product_id, $from_wh, $to_wh, $quantity, $notes);
                $stmt->execute();
                $transId = $conn->insert_id;

                $ref = "TRF-" . $transId;
                $stmt = $conn->prepare("INSERT INTO stock_ledger (product_id, transaction_type, quantity, reference, notes) VALUES (?, 'transfer', ?, ?, ?)");
                $stmt->bind_param("iiss", $product_id, $quantity, $ref, $notes);
                $stmt->execute();

                $conn->commit();
                $success = 'Transfer recorded successfully.';
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

$products = $conn->query("SELECT id, name, sku, stock, unit FROM products WHERE stock > 0 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$warehouses = $conn->query("SELECT id, name FROM warehouses ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Prepare display names so duplicate warehouse names get A/B suffixes
$whNameCounts = [];
foreach ($warehouses as $w) {
    $name = $w['name'] ?? '';
    if ($name !== '') {
        $whNameCounts[$name] = ($whNameCounts[$name] ?? 0) + 1;
    }
}
$whNameIndex = [];
$transfers = $conn->query("
    SELECT t.*, p.name as product_name, p.sku 
    FROM transfers t 
    JOIN products p ON p.id = t.product_id 
    ORDER BY t.created_at DESC 
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-arrow-left-right text-warning"></i> Internal Transfers</h2>
</div>

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> New Transfer</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select product...</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['sku'] ?>) - Stock: <?= $p['stock'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">From Warehouse</label>
                            <select name="from_warehouse_id" class="form-select">
                                <option value="">-</option>
                                <?php foreach ($warehouses as $w): ?>
                                    <?php
                                    $name = $w['name'] ?? '';
                                    if ($name !== '' && ($whNameCounts[$name] ?? 0) > 1) {
                                        $whNameIndex[$name] = ($whNameIndex[$name] ?? 0) + 1;
                                        $suffix = chr(ord('A') + $whNameIndex[$name] - 1);
                                        $displayName = $name . ' ' . $suffix;
                                    } else {
                                        $displayName = $name;
                                    }
                                    ?>
                                    <option value="<?= $w['id'] ?>"><?= htmlspecialchars($displayName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">To Warehouse</label>
                            <select name="to_warehouse_id" class="form-select">
                                <option value="">-</option>
                                <?php foreach ($warehouses as $w): ?>
                                    <?php
                                    $name = $w['name'] ?? '';
                                    if ($name !== '' && ($whNameCounts[$name] ?? 0) > 1) {
                                        $whNameIndex[$name] = ($whNameIndex[$name] ?? 0) + 1;
                                        $suffix = chr(ord('A') + $whNameIndex[$name] - 1);
                                        $displayName = $name . ' ' . $suffix;
                                    } else {
                                        $displayName = $name;
                                    }
                                    ?>
                                    <option value="<?= $w['id'] ?>"><?= htmlspecialchars($displayName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control" min="1" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-check-lg"></i> Record Transfer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Recent Transfers</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transfers)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No transfers yet.</td></tr>
                            <?php else: ?>
                            <?php foreach ($transfers as $t): ?>
                            <tr>
                                <td>TRF-<?= $t['id'] ?></td>
                                <td><?= htmlspecialchars($t['product_name']) ?> <small class="text-muted">(<?= $t['sku'] ?>)</small></td>
                                <td><span class="badge bg-warning text-dark"><?= $t['quantity'] ?></span></td>
                                <td><?= date('M d, Y H:i', strtotime($t['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
