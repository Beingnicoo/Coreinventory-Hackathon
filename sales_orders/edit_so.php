<?php
/**
 * Edit Sales Order
 * Only draft orders can be edited
 */
$pageTitle = 'Edit Sales Order - CoreInventory';
$currentPage = 'sales_orders';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$error = '';
$userId = getUserId();

if (!$id) {
    header('Location: so_list.php');
    exit;
}

// Get SO data
$stmt = $conn->prepare("SELECT * FROM sales_orders WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$so = $stmt->get_result()->fetch_assoc();

if (!$so || $so['status'] !== 'draft') {
    header('Location: view_so.php?id=' . $id);
    exit;
}

// Get existing items
$itemsStmt = $conn->prepare("SELECT * FROM sales_order_items WHERE so_id = ?");
$itemsStmt->bind_param("i", $id);
$itemsStmt->execute();
$existingItems = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $order_date = $_POST['order_date'] ?? date('Y-m-d');
    $delivery_date = $_POST['delivery_date'] ?? null;
    $notes = trim($_POST['notes'] ?? '');
    $items = $_POST['items'] ?? [];

    if (!$customer_id) {
        $error = 'Please select a customer.';
    } elseif (empty($items) || !is_array($items)) {
        $error = 'Please add at least one item to the sales order.';
    } else {
        $conn->begin_transaction();
        try {
            // Update SO
            $stmt = $conn->prepare("UPDATE sales_orders SET customer_id = ?, order_date = ?, delivery_date = ?, notes = ? WHERE id = ?");
            $stmt->bind_param("isssi", $customer_id, $order_date, $delivery_date, $notes, $id);
            $stmt->execute();

            // Delete existing items
            $conn->query("DELETE FROM sales_order_items WHERE so_id = $id");

            // Add new items
            $total = 0;
            foreach ($items as $item) {
                $product_id = (int)($item['product_id'] ?? 0);
                $quantity = (int)($item['quantity'] ?? 0);
                $unit_price = floatval($item['unit_price'] ?? 0);

                if ($product_id && $quantity > 0) {
                    $itemStmt = $conn->prepare("INSERT INTO sales_order_items (so_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                    $itemStmt->bind_param("iiid", $id, $product_id, $quantity, $unit_price);
                    $itemStmt->execute();
                    $total += $quantity * $unit_price;
                }
            }

            // Update total
            $updateStmt = $conn->prepare("UPDATE sales_orders SET total_amount = ? WHERE id = ?");
            $updateStmt->bind_param("di", $total, $id);
            $updateStmt->execute();

            $conn->commit();
            header('Location: view_so.php?id=' . $id . '&success=updated');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Error updating sales order: ' . $e->getMessage();
        }
    }
}

$customersResult = $conn->query("SELECT id, name, code FROM customers WHERE status = 'active' ORDER BY name");
$customers = $customersResult ? $customersResult->fetch_all(MYSQLI_ASSOC) : [];

$productsResult = $conn->query("SELECT id, name, sku, unit, stock, selling_price FROM products ORDER BY name");
$products = $productsResult ? $productsResult->fetch_all(MYSQLI_ASSOC) : [];

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-pencil text-primary"></i> Edit Sales Order: <?= htmlspecialchars($so['so_number']) ?></h2>
    <a href="<?= htmlspecialchars($base) ?>/sales_orders/view_so.php?id=<?= $id ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" id="soForm">
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Order Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">Select customer...</option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($so['customer_id'] == $c['id'] || (isset($_POST['customer_id']) && $_POST['customer_id'] == $c['id'])) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['code'] ?: '-') ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Order Date <span class="text-danger">*</span></label>
                            <input type="date" name="order_date" class="form-control" required value="<?= htmlspecialchars($_POST['order_date'] ?? $so['order_date']) ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Delivery Date</label>
                            <input type="date" name="delivery_date" class="form-control" value="<?= htmlspecialchars($_POST['delivery_date'] ?? $so['delivery_date']) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($_POST['notes'] ?? $so['notes']) ?></textarea>
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
                                <?php 
                                $itemIndex = 0;
                                foreach ($existingItems as $item): 
                                ?>
                                <tr class="item-row">
                                    <td>
                                        <select name="items[<?= $itemIndex ?>][product_id]" class="form-select product-select" required onchange="updateProductInfo(this)">
                                            <option value="">Select product...</option>
                                            <?php foreach ($products as $p): ?>
                                            <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock'] ?>" data-price="<?= $p['selling_price'] ?>" <?= $item['product_id'] == $p['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($p['name']) ?> (<?= $p['sku'] ?>) - Stock: <?= $p['stock'] ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" name="items[<?= $itemIndex ?>][quantity]" class="form-control quantity" min="1" required value="<?= $item['quantity'] ?>"></td>
                                    <td><input type="number" name="items[<?= $itemIndex ?>][unit_price]" class="form-control unit-price" step="0.01" min="0" required value="<?= $item['unit_price'] ?>"></td>
                                    <td><span class="item-total"><?= number_format($item['quantity'] * $item['unit_price'], 2) ?></span></td>
                                    <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
                                </tr>
                                <?php 
                                $itemIndex++;
                                endforeach; 
                                ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">Grand Total:</th>
                                    <th id="grandTotal">₹<?= number_format($so['total_amount'], 2) ?></th>
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
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Sales Order</button>
        <a href="<?= htmlspecialchars($base) ?>/sales_orders/view_so.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
let itemIndex = <?= $itemIndex ?>;

function addItemRow() {
    const tbody = document.getElementById('itemsBody');
    const row = document.createElement('tr');
    row.className = 'item-row';
    row.innerHTML = `
        <td>
            <select name="items[${itemIndex}][product_id]" class="form-select product-select" required onchange="updateProductInfo(this)">
                <option value="">Select product...</option>
                <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock'] ?>" data-price="<?= $p['selling_price'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['sku'] ?>) - Stock: <?= $p['stock'] ?></option>
                <?php endforeach; ?>
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

function updateProductInfo(select) {
    const option = select.options[select.selectedIndex];
    const price = option.getAttribute('data-price') || 0;
    const row = select.closest('tr');
    row.querySelector('.unit-price').value = price;
    calculateTotal();
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

document.querySelectorAll('.item-row').forEach(row => attachEventListeners(row));
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

