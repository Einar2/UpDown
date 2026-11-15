<?php
// Стартуем сессию с безопасными настройками
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Генерируем CSRF-токен, если его нет
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Проверяем авторизацию
if (empty($_SESSION['admin'])) {
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

// Проверяем, что IP и User-Agent не менялись
if (($_SESSION['admin_ip'] ?? '') !== ($_SERVER['REMOTE_ADDR'] ?? '')
    || ($_SESSION['admin_ua'] ?? '') !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
    session_destroy();
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

/**
 * Проверка CSRF для POST-запросов.
 */
function checkCsrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403);
        die('CSRF token mismatch');
    }
}

/**
 * Хелпер для вывода в HTML.
 */
function ae(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}