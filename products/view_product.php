<?php
/**
 * View Product Details
 * Shows product information with barcode
 */
$pageTitle = 'View Product - CoreInventory';
$currentPage = 'products';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: product_list.php');
    exit;
}

// Get product data
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
if (!$stmt) {
    header('Location: product_list.php');
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header('Location: product_list.php');
    exit;
}

// Get stock ledger entries
$ledgerStmt = $conn->prepare("
    SELECT * FROM stock_ledger 
    WHERE product_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
");
$ledgerEntries = [];
if ($ledgerStmt) {
    $ledgerStmt->bind_param("i", $id);
    if ($ledgerStmt->execute()) {
        $result = $ledgerStmt->get_result();
        $ledgerEntries = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-box text-primary"></i> Product Details</h2>
    <div class="d-flex gap-2">
        <a href="<?= htmlspecialchars($base) ?>/products/edit_product.php?id=<?= $id ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Edit
        </a>
        <a href="<?= htmlspecialchars($base) ?>/products/product_list.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Product Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="40%">Name:</th>
                        <td><strong><?= htmlspecialchars($product['name']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>SKU:</th>
                        <td><code><?= htmlspecialchars($product['sku']) ?></code></td>
                    </tr>
                    <tr>
                        <th>Category:</th>
                        <td><?= htmlspecialchars($product['category']) ?></td>
                    </tr>
                    <tr>
                        <th>Unit:</th>
                        <td><?= htmlspecialchars($product['unit']) ?></td>
                    </tr>
                    <tr>
                        <th>Stock:</th>
                        <td>
                            <?php if ($product['stock'] < 10 && $product['stock'] > 0): ?>
                                <span class="badge bg-danger"><?= $product['stock'] ?> <small>(Low Stock)</small></span>
                            <?php elseif ($product['stock'] == 0): ?>
                                <span class="badge bg-secondary"><?= $product['stock'] ?></span>
                            <?php else: ?>
                                <span class="badge bg-success"><?= $product['stock'] ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($product['cost_price'])): ?>
                    <tr>
                        <th>Cost Price:</th>
                        <td>₹<?= number_format($product['cost_price'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($product['selling_price'])): ?>
                    <tr>
                        <th>Selling Price:</th>
                        <td>₹<?= number_format($product['selling_price'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($product['reorder_point'])): ?>
                    <tr>
                        <th>Reorder Point:</th>
                        <td><?= $product['reorder_point'] ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-upc-scan"></i> Barcode</h5>
                <button class="btn btn-sm btn-primary" onclick="downloadBarcode('<?= htmlspecialchars($product['sku']) ?>', '<?= htmlspecialchars($product['name']) ?>')">
                    <i class="bi bi-download"></i> Download
                </button>
            </div>
            <div class="card-body text-center">
                <svg id="product-barcode" class="barcode"></svg>
                <p class="text-muted mt-3 mb-0">SKU: <code><?= htmlspecialchars($product['sku']) ?></code></p>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-journal-text"></i> Recent Stock Movements</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Reference</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ledgerEntries)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No stock movements yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($ledgerEntries as $entry): ?>
                    <tr>
                        <td><?= date('M d, Y H:i', strtotime($entry['created_at'])) ?></td>
                        <td>
                            <?php
                            $typeColors = [
                                'receipt' => 'success',
                                'delivery' => 'danger',
                                'transfer' => 'warning',
                                'adjustment' => 'info'
                            ];
                            ?>
                            <span class="badge bg-<?= $typeColors[$entry['transaction_type']] ?? 'secondary' ?>">
                                <?= ucfirst($entry['transaction_type']) ?>
                            </span>
                        </td>
                        <td><?= $entry['quantity'] ?></td>
                        <td><code><?= htmlspecialchars($entry['reference'] ?: '-') ?></code></td>
                        <td><?= htmlspecialchars($entry['notes'] ?: '-') ?></td>
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
// Generate barcode
JsBarcode("#product-barcode", "<?= htmlspecialchars($product['sku']) ?>", {
    format: "CODE128",
    width: 2,
    height: 60,
    displayValue: true,
    fontSize: 14
});

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

