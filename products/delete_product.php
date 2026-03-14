<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $conn->query("DELETE FROM stock_ledger WHERE product_id = $id");
    $conn->query("DELETE FROM receipts WHERE product_id = $id");
    $conn->query("DELETE FROM deliveries WHERE product_id = $id");
    $conn->query("DELETE FROM transfers WHERE product_id = $id");
    $conn->query("DELETE FROM adjustments WHERE product_id = $id");
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

$base = dirname($_SERVER['PHP_SELF'], 2);
if ($base === '\\' || $base === '.') $base = '';
header("Location: " . $base . "/products/product_list.php");
exit;
