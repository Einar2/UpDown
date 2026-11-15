<?php
if (!isset($pageTitle)) $pageTitle = 'Админка';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= ae($pageTitle) ?> — Админка</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/admin.css">
</head>
<body class="admin-body">

<header class="admin-header">
    <div class="admin-header__inner">
        <a href="<?= BASE_URL ?>/admin/products.php" class="admin-header__logo">
            Вверх — Вниз <span>админка</span>
        </a>
        <nav class="admin-nav">
            <a href="<?= BASE_URL ?>/admin/products.php">Товары</a>
            <a href="<?= BASE_URL ?>/admin/orders.php">Заказы</a>
            <a href="<?= BASE_URL ?>/" target="_blank">На сайт ↗</a>
            <a href="<?= BASE_URL ?>/admin/logout.php">Выйти</a>
        </nav>
    </div>
</header>

<main class="admin-main"></main>