<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/cart.php';

cartInit();

if (!isset($pageTitle))
    $pageTitle = 'Вверх-Вниз';


if (!isset($categories)) {
    $categories = $pdo->query("
        SELECT id, name, slug, icon, parent_id
        FROM categories
        WHERE parent_id IS NULL
        ORDER BY sort_order, id
    ")->fetchAll();

    error_log('header.php categories count: ' . count($categories));


}



$cartCount = cartCount();
$base = BASE_URL;
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> — Вверх-Вниз</title>
    <link rel="stylesheet" href="<?= $base ?>../assets/css/style.css">
    <link rel="icon" href="<?= $base ?>/assets/img/favicon.ico">

    <!-- fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
</head>

<body>

    <!-- Модалка 18+ -->
    <div class="age-modal" id="ageModal" hidden>
        <div class="age-modal__box">
            <div class="modal_logo">
                <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="">

            </div>

            

            <p class="age-modal__text">
                Данный сайт содержит материалы,<br>предназначенные только для совершеннолетних.
            </p>

            <p class="age-modal__text">
                Пожалуйста, подтвердите свой возраст, чтобы продолжить.
            </p>

            <div class="age-modal__icon">
                <img src="<?= BASE_URL ?>/assets/img/18.png" alt="">
            </div>


            <button class="btn btn--age" id="ageYes">Да, мне есть 18</button>
            <button class="btn btn--age" id="ageNo">Нет, я младше</button>
        </div>
    </div>

    <header class="site-header">
        <div class="site-header__top container">
            <a href="<?= $base ?>/" class="site-header__logo"><img src="<?= $base ?>/assets/img/logo.svg" alt=""
                    width="99" height="65" loading="lazy"></a>

            <form class="site-header__search" action="<?= $base ?>/" method="get" role="search">
                <input type="text" name="q" placeholder="Поиск..." value="<?= e($_GET['q'] ?? '') ?>"
                    aria-label="Поиск по товарам">
                <button type="submit" aria-label="Найти">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7" />
                        <path d="m20 20-3.5-3.5" />
                    </svg>
                </button>
            </form>
            <div class="header_btns_right">
                <a href="<?= $base ?>/" class="site-header__link">Главная</a>
                <a href="https://go.2gis.com/PKmrT" class="site-header__address">г. Ставрополь ул. Голенева 28/50</a>

                <a href="<?= $base ?>/cart.php" class="site-header__cart" aria-label="Корзина">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 4h2l2.4 12.2a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21 7H6" />
                        <circle cx="10" cy="21" r="1.4" />
                        <circle cx="18" cy="21" r="1.4" />
                    </svg>
                    <span class="site-header__cart-count" id="cartCount">
                        <?= (int) $cartCount ?>
                    </span>
                </a>
            </div>



        </div>

        <nav class="site-nav">
            <div class="site-nav__inner container">

                <a href="<?= $base ?>/?promo=1" class="site-nav__item<?= !empty($_GET['promo']) ? ' is-active' : '' ?>">
                    <img src="<?= $base ?>/assets/img/discount.svg" alt="" width="30" height="30" loading="lazy">
                    <span>Акции</span>
                </a>

                <?php foreach ($categories as $c): ?>
                    <a href="<?= $base ?>/?category=<?= urlencode($c['slug']) ?>"
                        class="site-nav__item<?= (($_GET['category'] ?? '') === $c['slug']) ? ' is-active' : '' ?>">
                        <?php if (!empty($c['icon'])): ?>
                            <img src="<?= $base . e($c['icon']) ?>" alt="" width="22" height="22" loading="lazy">
                        <?php endif; ?>
                        <span><?= e($c['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>
    </header>

    <main class="site-main">
        <div class="container">