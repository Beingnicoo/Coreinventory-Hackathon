<?php
$pageTitle = 'Stock Adjustments - CoreInventory';
$currentPage = 'adjustments';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $counted_stock = (int)($_POST['counted_stock'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if (!$product_id) {
        $error = 'Please select a product.';
    } else {
        $stmt = $conn->prepare("SELECT id, name, stock FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        if (!$product) {
            $error = 'Product not found.';
        } else {
            $previous_stock = (int)$product['stock'];
            $difference = $counted_stock - $previous_stock;

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO adjustments (product_id, previous_stock, counted_stock, difference, notes) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiiis", $product_id, $previous_stock, $counted_stock, $difference, $notes);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
                $stmt->bind_param("ii", $counted_stock, $product_id);
                $stmt->execute();

                $ref = "ADJ-" . $conn->insert_id;
                $stmt = $conn->prepare("INSERT INTO stock_ledger (product_id, transaction_type, quantity, reference, notes) VALUES (?, 'adjustment', ?, ?, ?)");
                $stmt->bind_param("iiss", $product_id, $difference, $ref, $notes);
                $stmt->execute();

                $conn->commit();
                $success = "Adjustment saved. Stock: $previous_stock → $counted_stock (diff: " . ($difference >= 0 ? "+" : "") . "$difference)";
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

$products = $conn->query("SELECT id, name, sku, stock, unit FROM products ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$adjustments = $conn->query("
    SELECT a.*, p.name as product_name, p.sku 
    FROM adjustments a 
    JOIN products p ON p.id = a.product_id 
    ORDER BY a.created_at DESC 
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-sliders text-secondary"></i> Stock Adjustments</h2>
</div>

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> New Adjustment</h5>
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
                        <select name="product_id" class="form-select" required id="prodSelect">
                            <option value="">Select product...</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock'] ?>">
                                    <?= htmlspecialchars($p['name']) ?> (<?= $p['sku'] ?>) - Current: <?= $p['stock'] ?> <?= $p['unit'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Stock (system)</label>
                        <input type="text" class="form-control" id="currentStock" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Counted Stock <span class="text-danger">*</span></label>
                        <input type="number" name="counted_stock" class="form-control" min="0" required id="countedInput">
                        <small class="text-muted" id="diffHint">Difference: -</small>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Reason for adjustment"></textarea>
                    </div>
                    <button type="submit" class="btn btn-secondary"><i class="bi bi-check-lg"></i> Save Adjustment</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Recent Adjustments</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Previous</th>
                                <th>Counted</th>
                                <th>Diff</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($adjustments)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No adjustments yet.</td></tr>
                            <?php else: ?>
                            <?php foreach ($adjustments as $a): ?>
                            <tr>
                                <td>ADJ-<?= $a['id'] ?></td>
                                <td><?= htmlspecialchars($a['product_name']) ?> <small class="text-muted">(<?= $a['sku'] ?>)</small></td>
                                <td><?= $a['previous_stock'] ?></td>
                                <td><?= $a['counted_stock'] ?></td>
                                <td>
                                    <?php $d = (int)$a['difference']; ?>
                                    <span class="badge <?= $d >= 0 ? 'bg-success' : 'bg-danger' ?>"><?= $d >= 0 ? '+' : '' ?><?= $d ?></span>
                                </td>
                                <td><?= date('M d, Y H:i', strtotime($a['created_at'])) ?></td>
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
<script>
const sel = document.getElementById('prodSelect');
const current = document.getElementById('currentStock');
const counted = document.getElementById('countedInput');
const hint = document.getElementById('diffHint');
function update() {
    const opt = sel.options[sel.selectedIndex];
    const stock = opt ? parseInt(opt.dataset.stock || 0) : 0;
    current.value = stock;
    const cnt = parseInt(counted.value) || 0;
    const diff = cnt - stock;
    hint.textContent = 'Difference: ' + (diff >= 0 ? '+' : '') + diff;
    hint.className = diff >= 0 ? 'text-success' : 'text-danger';
}
sel.addEventListener('change', update);
counted.addEventListener('input', update);
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
