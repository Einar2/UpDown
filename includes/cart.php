<?php
/**
 * Корзина хранится в $_SESSION['cart'] в виде [product_id => qty].
 */

function cartInit(): void
{
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
}

function cartAdd(int $productId, int $qty = 1): void
{
    cartInit();
    if ($qty < 1) $qty = 1;
    $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + $qty;
}

function cartUpdate(int $productId, int $qty): void
{
    cartInit();
    if ($qty < 1) {
        cartRemove($productId);
        return;
    }
    $_SESSION['cart'][$productId] = $qty;
}

function cartRemove(int $productId): void
{
    cartInit();
    unset($_SESSION['cart'][$productId]);
}

function cartClear(): void
{
    $_SESSION['cart'] = [];
}

function cartCount(): int
{
    cartInit();
    return array_sum($_SESSION['cart']);
}

/**
 * Возвращает товары корзины + пересчитанные суммы.
 * Цена берётся из БД, а не из сессии — защита от подмены.
 */
function cartItems(PDO $pdo): array
{
    cartInit();
    if (empty($_SESSION['cart'])) return [];

    $ids = array_map('intval', array_keys($_SESSION['cart']));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT id, name, price, discount
        FROM products
        WHERE id IN ($placeholders) AND is_active = 1
    ");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    attachImages($pdo, $rows);

    $items = [];
    $total = 0.0;
    foreach ($rows as $row) {
        $qty = (int)$_SESSION['cart'][$row['id']];
        $price = finalPrice($row);
        $sum = $price * $qty;
        $total += $sum;

        $items[] = [
            'id'    => (int)$row['id'],
            'name'  => $row['name'],
            'price' => $price,
            'qty'   => $qty,
            'sum'   => $sum,
            'image' => $row['images'][0] ?? null,
        ];
    }

    return ['items' => $items, 'total' => $total];
}