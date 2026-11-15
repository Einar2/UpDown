<?php
require __DIR__ . '/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$csrf = $_GET['csrf'] ?? '';

if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
    http_response_code(403);
    die('CSRF token mismatch');
}

if ($id > 0) {
    // order_items уйдут каскадом (FK ON DELETE CASCADE)
    $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);
}

header('Location: ' . BASE_URL . '/admin/orders.php?deleted=1');
exit;