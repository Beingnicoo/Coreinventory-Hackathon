<?php
/**
 * Delete Customer
 * Handles customer deletion with safety checks
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: customer_list.php');
    exit;
}

// Check if customer is used in sales orders
$checkStmt = $conn->prepare("SELECT COUNT(*) as c FROM sales_orders WHERE customer_id = ?");
$checkStmt->bind_param("i", $id);
$checkStmt->execute();
$count = $checkStmt->get_result()->fetch_assoc()['c'];

if ($count > 0) {
    // Don't delete, just deactivate
    $stmt = $conn->prepare("UPDATE customers SET status = 'inactive' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header('Location: customer_list.php?deactivated=1');
} else {
    // Safe to delete
    $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header('Location: customer_list.php?deleted=1');
}
exit;

