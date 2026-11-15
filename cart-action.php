<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id     = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$qty    = (int)($_POST['qty'] ?? $_GET['qty'] ?? 1);

if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Bad id']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Product not found']);
    exit;
}

switch ($action) {
    case 'add':    cartAdd($id, max(1, $qty)); break;
    case 'update': cartUpdate($id, $qty); break;
    case 'remove': cartRemove($id); break;
    default:
        echo json_encode(['ok' => false, 'error' => 'Bad action']);
        exit;
}

// Пересчитываем итог
$cart = cartItems($pdo);
$itemSum = 0;
foreach ($cart['items'] as $it) {
    if ($it['id'] === $id) { $itemSum = $it['sum']; break; }
}

echo json_encode([
    'ok'                 => true,
    'count'              => cartCount(),
    'total'              => $cart['total'],
    'total_formatted'    => formatPrice($cart['total']),
    'item_sum'           => $itemSum,
    'item_sum_formatted' => formatPrice($itemSum),
], JSON_UNESCAPED_UNICODE);