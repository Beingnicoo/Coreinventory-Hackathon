<?php
/**
 * Create Purchase Order
 * Form to create a new purchase order with multiple items
 */
$pageTitle = 'Create Purchase Order - CoreInventory';
$currentPage = 'purchase_orders';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$error = '';
$userId = getUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $order_date = $_POST['order_date'] ?? date('Y-m-d');
    $expected_date = $_POST['expected_date'] ?? null;
    $notes = trim($_POST['notes'] ?? '');
    $items = $_POST['items'] ?? [];

    if (!$supplier_id) {
        $error = 'Please select a supplier.';
    } elseif (empty($items) || !is_array($items)) {
        $error = 'Please add at least one item to the purchase order.';
    } else {
        // Generate PO number
        $poCount = $conn->query("SELECT COUNT(*) as c FROM purchase_orders")->fetch_assoc()['c'] ?? 0;
        $po_number = 'PO-' . date('Y') . '-' . str_pad($poCount + 1, 5, '0', STR_PAD_LEFT);

        $conn->begin_transaction();
        try {
            // Create PO
            $stmt = $conn->prepare("INSERT INTO purchase_orders (po_number, supplier_id, order_date, expected_date, notes, created_by, status) VALUES (?, ?, ?, ?, ?, ?, 'draft')");
            $stmt->bind_param("sissis", $po_number, $supplier_id, $order_date, $expected_date, $notes, $userId);
            $stmt->execute();
            $po_id = $conn->insert_id;

            // Add items
            $total = 0;
            foreach ($items as $item) {
                $product_id = (int)($item['product_id'] ?? 0);
                $quantity = (int)($item['quantity'] ?? 0);
                $unit_price = floatval($item['unit_price'] ?? 0);

                if ($product_id && $quantity > 0) {
                    $itemStmt = $conn->prepare("INSERT INTO purchase_order_items (po_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                    $itemStmt->bind_param("iiid", $po_id, $product_id, $quantity, $unit_price);
                    $itemStmt->execute();
                    $total += $quantity * $unit_price;
                }
            }

            // Update total
            $updateStmt = $conn->prepare("UPDATE purchase_orders SET total_amount = ? WHERE id = ?");
            $updateStmt->bind_param("di", $total, $po_id);
            $updateStmt->execute();

            $conn->commit();
            header('Location: view_po.php?id=' . $po_id . '&success=created');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Error creating purchase order: ' . $e->getMessage();
        }
    }
}

$suppliersResult = $conn->query("SELECT id, name, code FROM suppliers WHERE status = 'active' ORDER BY name");
$suppliers = $suppliersResult ? $suppliersResult->fetch_all(MYSQLI_ASSOC) : [];

$productsResult = $conn->query("SELECT id, name, sku, unit FROM products ORDER BY name");
$products = $productsResult ? $productsResult->fetch_all(MYSQLI_ASSOC) : [];

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-plus-circle text-primary"></i> Create Purchase Order</h2>
    <a href="<?= htmlspecialchars($base) ?>/purchase_orders/po_list.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" id="poForm">
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Order Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier <span class="text-danger">*</span></label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">Select supplier...</option>
                                <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $s['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['code'] ?: '-') ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Order Date <span class="text-danger">*</span></label>
                            <input type="date" name="order_date" class="form-control" required value="<?= htmlspecialchars($_POST['order_date'] ?? date('Y-m-d')) ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Expected Date</label>
                            <input type="date" name="expected_date" class="form-control" value="<?= htmlspecialchars($_POST['expected_date'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> Order Items</h5>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addItemRow()">
                        <i class="bi bi-plus"></i> Add Item
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th width="120">Quantity</th>
                                    <th width="120">Unit Price</th>
                                    <th width="120">Total</th>
                                    <th width="50"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <tr class="item-row">
                                    <td>
                                        <select name="items[0][product_id]" class="form-select product-select" required>
                                            <option value="">Select product...</option>
                                            <?php foreach ($products as $p): ?>
                                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['sku'] ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" name="items[0][quantity]" class="form-control quantity" min="1" required value="1"></td>
                                    <td><input type="number" name="items[0][unit_price]" class="form-control unit-price" step="0.01" min="0" required value="0"></td>
                                    <td><span class="item-total">0.00</span></td>
                                    <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">Grand Total:</th>
                                    <th id="grandTotal">₹0.00</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Create Purchase Order</button>
        <a href="<?= htmlspecialchars($base) ?>/purchase_orders/po_list.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
let itemIndex = 1;
const products = <?= json_encode($products) ?>;

function addItemRow() {
    const tbody = document.getElementById('itemsBody');
    const row = document.createElement('tr');
    row.className = 'item-row';
    row.innerHTML = `
        <td>
            <select name="items[${itemIndex}][product_id]" class="form-select product-select" required>
                <option value="">Select product...</option>
                ${products.map(p => `<option value="${p.id}">${p.name} (${p.sku})</option>`).join('')}
            </select>
        </td>
        <td><input type="number" name="items[${itemIndex}][quantity]" class="form-control quantity" min="1" required value="1"></td>
        <td><input type="number" name="items[${itemIndex}][unit_price]" class="form-control unit-price" step="0.01" min="0" required value="0"></td>
        <td><span class="item-total">0.00</span></td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
    `;
    tbody.appendChild(row);
    itemIndex++;
    attachEventListeners(row);
}

function removeRow(btn) {
    const tbody = document.getElementById('itemsBody');
    if (tbody.children.length > 1) {
        btn.closest('tr').remove();
        calculateTotal();
    }
}

function calculateTotal() {
    let grandTotal = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.quantity').value) || 0;
        const price = parseFloat(row.querySelector('.unit-price').value) || 0;
        const total = qty * price;
        row.querySelector('.item-total').textContent = '₹' + total.toFixed(2);
        grandTotal += total;
    });
    document.getElementById('grandTotal').textContent = '₹' + grandTotal.toFixed(2);
}

function attachEventListeners(row) {
    row.querySelector('.quantity').addEventListener('input', calculateTotal);
    row.querySelector('.unit-price').addEventListener('input', calculateTotal);
}

// Attach listeners to existing rows
document.querySelectorAll('.item-row').forEach(row => attachEventListeners(row));
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

