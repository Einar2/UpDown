<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$orderId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$pageTitle = 'Заказ оформлен';
require __DIR__ . '/includes/header.php';
?>

<div class="order-success">
    <div class="order-success__icon">✓</div>
    <h1 class="order-success__title">Заказ №<?= (int) $order['id'] ?> оформлен</h1>

    <p class="order-success__text">
        Мы отправили подтверждение на <b><?= e($order['customer_email']) ?></b>.
        <?php if ($order['delivery_type'] === 'pickup'): ?>
            Менеджер свяжется с вами для подтверждения.
        <?php else: ?>
            Менеджер свяжется с вами для уточнения деталей доставки.
        <?php endif; ?>
    </p>

    <?php if ($order['delivery_type'] === 'pickup' && $order['pickup_code']): ?>
        <div class="order-success__code">
            <div class="order-success__code-label">Код получения</div>
            <div class="order-success__code-value"><?= e($order['pickup_code']) ?></div>
            <div class="order-success__code-hint">
                Назовите этот код сотруднику пункта выдачи, чтобы получить заказ.
            </div>
        </div>

        <p class="order-success__address">
            <b>Адрес пункта выдачи:</b><br>
            г. Ставрополь, ул. Голенева, 28/50
        </p>
    <?php endif; ?>

    <?php if ($order['payment_type'] === 'cash'): ?>
        <p class="order-success__text">
            Оплата наличными при получении
            <?= $order['delivery_type'] === 'pickup' ? 'в пункте выдачи.' : 'курьеру.' ?>
        </p>
    <?php elseif ($order['payment_type'] === 'card'): ?>
        <p class="order-success__text">
            Оплата картой при получении
            <?= $order['delivery_type'] === 'pickup' ? 'в пункте выдачи (терминал).' : 'курьеру (терминал).' ?>
        </p>
    <?php elseif ($order['payment_type'] === 'transfer'): ?>
        <p class="order-success__text">
            Реквизиты для перевода придут на email. Заказ будет передан после подтверждения оплаты.
        </p>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>/" class="btn btn--outline">Вернуться в каталог</a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>