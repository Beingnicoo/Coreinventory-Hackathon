<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /odoo_web/login.php');
        exit;
    }
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}
