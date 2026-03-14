<?php
/**
 * Delete Supplier
 * Handles supplier deletion with safety checks
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: supplier_list.php');
    exit;
}

// Check if supplier is used in purchase orders
$checkStmt = $conn->prepare("SELECT COUNT(*) as c FROM purchase_orders WHERE supplier_id = ?");
$checkStmt->bind_param("i", $id);
$checkStmt->execute();
$count = $checkStmt->get_result()->fetch_assoc()['c'];

if ($count > 0) {
    // Don't delete, just deactivate
    $stmt = $conn->prepare("UPDATE suppliers SET status = 'inactive' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header('Location: supplier_list.php?deactivated=1');
} else {
    // Safe to delete
    $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header('Location: supplier_list.php?deleted=1');
}
exit;

