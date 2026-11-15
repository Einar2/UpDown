<?php

function formatPrice(float $price): string
{
    return number_format($price, 0, '.', ' ') . ' ₽';
}

function finalPrice(array $product): float
{
    $price = (float)$product['price'];
    $discount = (int)($product['discount'] ?? 0);
    return $discount > 0 ? $price * (1 - $discount / 100) : $price;
}

function e(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function shortText(?string $text, int $len = 70): string
{
    $text = trim(strip_tags($text ?? ''));
    if (mb_strlen($text) <= $len) return $text;
    return mb_substr($text, 0, $len) . '...';
}

/**
 * Достаёт картинки для массива товаров одним запросом.
 */
function attachImages(PDO $pdo, array &$products): void
{
    if (!$products) return;

    $ids = array_column($products, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT product_id, path
        FROM product_images
        WHERE product_id IN ($placeholders)
        ORDER BY product_id, sort_order
    ");
    $stmt->execute($ids);

    $map = [];
    foreach ($stmt as $row) {
        $map[$row['product_id']][] = $row['path'];
    }
    foreach ($products as &$p) {
        $p['images'] = $map[$p['id']] ?? [];
    }
    unset($p);
}


function productImage(?string $path): string
{
    if ($path === null || $path === '') {
        return BASE_URL . '/assets/img/no-image.png';
    }
    return BASE_URL . $path;
}



function makeSlug(string $text): string
{
    $text = mb_strtolower(trim($text));

    $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh',
        'з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o',
        'п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c',
        'ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
    ];
    $text = strtr($text, $map);

    // Всё, что не латиница/цифры — в дефис
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');

    // Если в результате пусто — вернуть fallback
    if ($text === '') {
        $text = 'product-' . time();
    }

    return $text;
}