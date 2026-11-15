<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['admin'])) {
    header('Location: ' . BASE_URL . '/admin/products.php');
    exit;
}

$error = '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Проверяем лимит попыток
$stmt = $pdo->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE ip = ?");
$stmt->execute([$ip]);
$row = $stmt->fetch();

$blocked = false;
if ($row) {
    $elapsed = time() - strtotime($row['last_attempt']);
    if ((int)$row['attempts'] >= 5 && $elapsed < 900) {
        $blocked = true;
        $waitMin = ceil((900 - $elapsed) / 60);
        $error = "Слишком много попыток. Попробуйте через {$waitMin} мин.";
    } elseif ($elapsed >= 900) {
        // Сбрасываем счётчик
        $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);
        $row = null;
    }
}

if (!$blocked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');

    // Загружаем хеш из файла
    $storedHash = require __DIR__ . '/../includes/admin_password.php';

    if (password_verify($password, $storedHash)) {
        // Успешный вход
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['admin_ip'] = $ip;
        $_SESSION['admin_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Сбрасываем счётчик попыток
        $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);

        header('Location: ' . BASE_URL . '/admin/products.php');
        exit;
    } else {
        // Неудачная попытка
        $pdo->prepare("
            INSERT INTO login_attempts (ip, attempts, last_attempt)
            VALUES (?, 1, NOW())
            ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()
        ")->execute([$ip]);

        $error = 'Неверный пароль';
        usleep(500000); // задержка 0.5 сек против брутфорса
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход в админку</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/admin.css">
</head>
<body class="admin-login-body">
    <form class="admin-login" method="post" autocomplete="off">
        <h1 class="admin-login__title">Вход в админку</h1>

        <?php if ($error): ?>
            <div class="admin-alert admin-alert--error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <label class="admin-label">
            Пароль
            <input type="password" name="password" required autofocus
                   <?= $blocked ? 'disabled' : '' ?>>
        </label> 

        <button type="submit" class="admin-btn admin-btn--primary" <?= $blocked ? 'disabled' : '' ?>>
            Войти
        </button>

        <p class="admin-login__hint">
            <a href="<?= BASE_URL ?>/">← На сайт</a>
        </p>
    </form>
</body>
</html>