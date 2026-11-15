<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart.php';

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Товар не найден';
    require __DIR__ . '/includes/header.php';
    echo '<p>Товар не найден. <a href="' . BASE_URL . '/">Вернуться в каталог</a></p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$images = [$product];
attachImages($pdo, $images);
$images = $images[0]['images'];

$price = finalPrice($product);
$pageTitle = $product['name'];

require __DIR__ . '/includes/header.php';
?>

<div class="product">

    <div class="product__gallery">
        <?php if ($images): ?>
            <div class="product__thumbs">
                <?php foreach ($images as $i => $path): ?>
                    <button class="product__thumb js-thumb<?= $i === 0 ? ' is-active' : '' ?>"
                        data-src="<?= BASE_URL . e($path) ?>">
                        <img src="<?= BASE_URL . e($path) ?>" alt="">
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="product__main">
                 <img id="productMain" src="<?= BASE_URL . e($images[0]) ?>" alt="<?= e($product['name']) ?>">
            </div>
        <?php else: ?>
            <div class="product__main product__main--empty">
                <img id="productMain" src="<?= BASE_URL . e($images[0]) ?>" alt="<?= e($product['name']) ?>">
            </div>
        <?php endif; ?>
    </div>

    <div class="product__info">
        <div class="product__rating" aria-label="Рейтинг">
            <span class="stars">★★★★★</span>
        </div>

        <h1 class="product__title"><?= e($product['name']) ?></h1>

        <div class="product__price">
            <span class="product__price-label">Цена:</span>
            <?php if ((int) $product['discount'] > 0): ?>
                <s class="product__price-old"><?= formatPrice((float) $product['price']) ?></s>
            <?php endif; ?>
            <span class="product__price-final"><?= formatPrice($price) ?></span>
        </div>

        <div class="product__delivery">
            <div class="product__delivery-item">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z" />
                    <circle cx="7" cy="17" r="2" />
                    <circle cx="17" cy="17" r="2" />
                </svg>
                <span>Доставка</span>
            </div>
            <div class="product__delivery-item">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 10 12 3l9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" />
                </svg>
                <span>Самовывоз</span>
            </div>
        </div>

        <div class="product__description">
            <?= nl2br(e($product['description'])) ?>
        </div>

        <button class="btn btn--cart btn--cart-big js-add-to-cart" data-id="<?= (int) $product['id'] ?>" type="button">
            В корзину
        </button>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>