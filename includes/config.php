<?php
$host = $_SERVER['HTTP_HOST'] ?? '';
if (str_contains($host, 'localhost') || str_contains($host, 'shop.local')) {
    require __DIR__ . '/config.local.php';
} else {
    require __DIR__ . '/config.prod.php';   // ← не существует
}