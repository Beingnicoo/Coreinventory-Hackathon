<?php
$pageTitle = 'Delivery - CoreInventory';
$currentPage = 'delivery';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $customer = trim($_POST['customer'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if (!$product_id || $quantity <= 0) {
        $error = 'Please select a product and enter a valid quantity.';
    } else {
        $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        if (!$product) {
            $error = 'Product not found.';
        } elseif ($product['stock'] < $quantity) {
            $error = 'Insufficient stock. Available: ' . $product['stock'];
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO deliveries (product_id, customer, quantity, notes) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isis", $product_id, $customer, $quantity, $notes);
                $stmt->execute();
                $deliveryId = $conn->insert_id;

                $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                $stmt->bind_param("ii", $quantity, $product_id);
                $stmt->execute();

                $ref = "DEL-" . $deliveryId;
                $stmt = $conn->prepare("INSERT INTO stock_ledger (product_id, transaction_type, quantity, reference, notes) VALUES (?, 'delivery', ?, ?, ?)");
                $qtyNeg = -$quantity;
                $stmt->bind_param("iiss", $product_id, $qtyNeg, $ref, $notes);
                $stmt->execute();

                $conn->commit();
                $success = 'Delivery saved. Stock decreased by ' . $quantity . '.';
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

$products = $conn->query("SELECT id, name, sku, stock, unit FROM products WHERE stock > 0 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$deliveries = $conn->query("
    SELECT d.*, p.name as product_name, p.sku 
    FROM deliveries d 
    JOIN products p ON p.id = d.product_id 
    ORDER BY d.created_at DESC 
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

$preselectProduct = (int)($_GET['product'] ?? 0);
$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-box-arrow-up text-info"></i> Delivery Orders (Stock Out)</h2>
</div>

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> New Delivery</h5>
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
                                <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock'] ?>" <?= $preselectProduct === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?> (<?= $p['sku'] ?>) - Stock: <?= $p['stock'] ?> <?= $p['unit'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <input type="text" name="customer" class="form-control" placeholder="Customer name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control" min="1" required id="qtyInput">
                        <small class="text-muted" id="stockHint">Available: -</small>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-info text-white"><i class="bi bi-check-lg"></i> Save Delivery</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Recent Deliveries</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Customer</th>
                                <th>Qty</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deliveries)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No deliveries yet.</td></tr>
                            <?php else: ?>
                            <?php foreach ($deliveries as $d): ?>
                            <tr>
                                <td>DEL-<?= $d['id'] ?></td>
                                <td><?= htmlspecialchars($d['product_name']) ?> <small class="text-muted">(<?= $d['sku'] ?>)</small></td>
                                <td><?= htmlspecialchars($d['customer'] ?: '-') ?></td>
                                <td><span class="badge bg-info">-<?= $d['quantity'] ?></span></td>
                                <td><?= date('M d, Y H:i', strtotime($d['created_at'])) ?></td>
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
document.getElementById('prodSelect').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const stock = opt ? opt.dataset.stock || 0 : 0;
    document.getElementById('stockHint').textContent = 'Available: ' + stock;
});
document.getElementById('qtyInput').addEventListener('input', function() {
    const opt = document.getElementById('prodSelect').options[document.getElementById('prodSelect').selectedIndex];
    const stock = parseInt(opt ? opt.dataset.stock || 0 : 0);
    const qty = parseInt(this.value) || 0;
    const hint = document.getElementById('stockHint');
    if (qty > stock) {
        hint.innerHTML = '<span class="text-danger">Insufficient! Available: ' + stock + '</span>';
    } else {
        hint.textContent = 'Available: ' + stock;
    }
});
document.getElementById('prodSelect').dispatchEvent(new Event('change'));
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
