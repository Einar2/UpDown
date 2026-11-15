<?php
require __DIR__ . '/auth.php';

$id = (int)($_GET['id'] ?? 0);
$csrf = $_GET['csrf'] ?? '';

if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
    http_response_code(403);
    die('CSRF token mismatch');
}

if ($id > 0) {
    // Удаляем файлы картинок
    $stmt = $pdo->prepare("SELECT path FROM product_images WHERE product_id = ?");
    $stmt->execute([$id]);
    foreach ($stmt as $img) {
        $full = __DIR__ . '/..' . $img['path'];
        if (is_file($full)) @unlink($full);
    }

    // Удаляем записи (product_images уйдут каскадом)
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
}

header('Location: ' . BASE_URL . '/admin/products.php?deleted=1');
exit;