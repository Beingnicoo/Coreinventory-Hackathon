<?php
$pageTitle = 'Receipts - CoreInventory';
$currentPage = 'receipts';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $supplier = trim($_POST['supplier'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if (!$product_id || $quantity <= 0) {
        $error = 'Please select a product and enter a valid quantity.';
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO receipts (product_id, supplier, quantity, notes) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isis", $product_id, $supplier, $quantity, $notes);
            $stmt->execute();
            $receiptId = $conn->insert_id;

            $stmt = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            $stmt->bind_param("ii", $quantity, $product_id);
            $stmt->execute();

            $ref = "REC-" . $receiptId;
            $stmt = $conn->prepare("INSERT INTO stock_ledger (product_id, transaction_type, quantity, reference, notes) VALUES (?, 'receipt', ?, ?, ?)");
            $stmt->bind_param("iiss", $product_id, $quantity, $ref, $notes);
            $stmt->execute();

            $conn->commit();
            $success = 'Receipt saved. Stock increased by ' . $quantity . '.';
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$products = $conn->query("SELECT id, name, sku, stock, unit FROM products ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$receipts = $conn->query("
    SELECT r.*, p.name as product_name, p.sku 
    FROM receipts r 
    JOIN products p ON p.id = r.product_id 
    ORDER BY r.created_at DESC 
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

$preselectProduct = (int)($_GET['product'] ?? 0);
$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-box-arrow-in-down text-success"></i> Receipts (Stock In)</h2>
</div>

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> New Receipt</h5>
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
                                <option value="<?= $p['id'] ?>" <?= $preselectProduct === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?> (<?= $p['sku'] ?>) - Stock: <?= $p['stock'] ?> <?= $p['unit'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Supplier</label>
                        <input type="text" name="supplier" class="form-control" placeholder="Supplier name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control" min="1" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Save Receipt</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Recent Receipts</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Supplier</th>
                                <th>Qty</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($receipts)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No receipts yet.</td></tr>
                            <?php else: ?>
                            <?php foreach ($receipts as $r): ?>
                            <tr>
                                <td>REC-<?= $r['id'] ?></td>
                                <td><?= htmlspecialchars($r['product_name']) ?> <small class="text-muted">(<?= $r['sku'] ?>)</small></td>
                                <td><?= htmlspecialchars($r['supplier'] ?: '-') ?></td>
                                <td><span class="badge bg-success">+<?= $r['quantity'] ?></span></td>
                                <td><?= date('M d, Y H:i', strtotime($r['created_at'])) ?></td>
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
