<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart.php';

$cart = cartItems($pdo);
$items = $cart['items'] ?? [];
$total = $cart['total'] ?? 0;

$pageTitle = 'Корзина';
require __DIR__ . '/includes/header.php';
?>

<h1 class="page-title">Корзина</h1>

<?php if (!$items): ?>

    <div class="cart-empty">
        <div class="cart-empty__icon">
            <svg viewBox="0 0 80 80" width="120" height="120" fill="none" stroke="#4a6cff" stroke-width="4">
                <path d="M10 14h6l8 40h40l8-30H22" />
                <circle cx="30" cy="66" r="5" />
                <circle cx="58" cy="66" r="5" />
                <path d="M32 34c2-3 6-3 8 0M46 34c2-3 6-3 8 0" stroke="#4a6cff" />
            </svg>
        </div>
        <h2 class="cart-empty__title">Похоже что ваша корзина пуста</h2>
        <a href="<?= BASE_URL ?>/" class="btn btn--outline">В каталог</a>
    </div>

<?php else: ?>

    <div class="cart-layout">
        <div class="cart-list">
            <?php foreach ($items as $it): ?>
                <div class="cart-item" data-id="<?= (int) $it['id'] ?>">
                    <div class="cart-item__image">
                        <img src="<?= e(productImage($it['image'] ?? null)) ?>" alt="<?= e($it['name']) ?>">
                    </div>
                    <div class="cart-item__body">
                        <h3 class="cart-item__name"><?= e($it['name']) ?></h3>
                        <div class="cart-item__controls">
                            <button class="qty-btn js-qty-minus" type="button" aria-label="Меньше">−</button>
                            <input type="text" class="qty-input js-qty" value="<?= (int) $it['qty'] ?>" readonly>
                            <button class="qty-btn js-qty-plus" type="button" aria-label="Больше">+</button>
                        </div>
                    </div>
                    <div class="cart-item__price">
                        <span class="cart-item__price-label">Цена:</span>
                        <span class="cart-item__price-value"><?= formatPrice($it['sum']) ?></span>
                    </div>
                    <button class="cart-item__remove js-remove" type="button" aria-label="Удалить">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#ff2d8a" stroke-width="2">
                            <path
                                d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13" />
                        </svg>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <aside class="cart-summary">
            <div class="cart-summary__box">
                <div class="cart-summary__row">
                    <span>Итого:</span>
                    <b id="cartTotal"><?= formatPrice($total) ?></b>
                </div>
                <a href="<?= BASE_URL ?>/checkout.php" class="btn btn--cart btn--cart-big">
                    Заказать
                </a>
            </div>
        </aside>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>