<?php
require __DIR__ . '/auth.php';

// Загружаем заказы с позициями — одним запросом товаров, потом раскладываем по заказам
$orders = $pdo->query("
    SELECT id, customer_name, customer_phone, customer_email,
           delivery_type, address, payment_type, pickup_code,
           total, status, comment, created_at
    FROM orders
    ORDER BY id DESC
")->fetchAll();

// Подтягиваем позиции одним запросом
$orderIds = array_column($orders, 'id');
$itemsByOrder = [];
if ($orderIds) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt = $pdo->prepare("
        SELECT order_id, product_name, price, qty
        FROM order_items
        WHERE order_id IN ($placeholders)
        ORDER BY id
    ");
    $stmt->execute($orderIds);
    foreach ($stmt as $row) {
        $itemsByOrder[$row['order_id']][] = $row;
    }
}

$pageTitle = 'Заказы';
require __DIR__ . '/admin-header.php';

$statuses = [
    'new' => 'Новый',
    'confirmed' => 'Подтверждён',
    'paid' => 'Оплачен',
    'delivered' => 'Доставлен',
    'cancelled' => 'Отменён',
];
$payments = [
    'cash' => 'Наличными при получении',
    'transfer' => 'Переводом по реквизитам',
    'card' => 'Картой при получении (терминал)',
];
?>

<div class="admin-top">
    <h1 class="admin-h1">Заказы (<?= count($orders) ?>)</h1>
</div>

<?php if (!empty($_GET['deleted'])): ?>
    <div class="admin-alert admin-alert--ok">Заказ удалён</div>
<?php endif; ?>

<?php if (!$orders): ?>
    <p class="admin-hint" style="margin-top:20px;">Заказов пока нет.</p>
<?php else: ?>

<div class="admin-orders">
    <?php foreach ($orders as $o): ?>
        <?php $items = $itemsByOrder[$o['id']] ?? []; ?>

        <details class="order">
            <summary class="order__summary">
                <span class="order__num">№<?= (int) $o['id'] ?></span>
                <span class="order__date"><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></span>
                <span class="order__name"><?= ae($o['customer_name']) ?></span>
                <span class="order__phone"><?= ae($o['customer_phone']) ?></span>
                <span class="order__total"><?= number_format((float) $o['total'], 0, '.', ' ') ?> ₽</span>
                <span class="order__status order__status--<?= ae($o['status']) ?>">
                    <?= $statuses[$o['status']] ?? ae($o['status']) ?>
                </span>
            </summary>

            <div class="order__body">

                <div class="order__grid">

                    <div class="order__block">
                        <h4 class="order__block-title">Покупатель</h4>
                        <p><b>Имя:</b> <?= ae($o['customer_name']) ?></p>
                        <p><b>Телефон:</b> <a href="tel:<?= ae($o['customer_phone']) ?>"><?= ae($o['customer_phone']) ?></a></p>
                        <p><b>Email:</b> <a href="mailto:<?= ae($o['customer_email']) ?>"><?= ae($o['customer_email']) ?></a></p>
                    </div>

                    <div class="order__block">
                        <h4 class="order__block-title">Доставка</h4>
                        <?php if ($o['delivery_type'] === 'pickup'): ?>
                            <p><b>Способ:</b> Самовывоз</p>
                            <p><b>Адрес ПВЗ:</b> г. Ставрополь, ул. Голенева, 28/50</p>
                            <?php if ($o['pickup_code']): ?>
                                <p><b>Код получения:</b>
                                    <span class="order__code"><?= ae($o['pickup_code']) ?></span>
                                </p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p><b>Способ:</b> Доставка</p>
                            <p><b>Адрес:</b> <?= ae($o['address']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="order__block">
                        <h4 class="order__block-title">Оплата</h4>
                        <p><?= $payments[$o['payment_type']] ?? ae($o['payment_type']) ?></p>
                        <p><b>Сумма:</b> <?= number_format((float) $o['total'], 0, '.', ' ') ?> ₽</p>
                    </div>

                    <div class="order__block">
                        <h4 class="order__block-title">Статус</h4>
                        <p><?= $statuses[$o['status']] ?? ae($o['status']) ?></p>
                        <p><b>Создан:</b> <?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></p>
                    </div>

                </div>

                <div class="order__block">
                    <h4 class="order__block-title">Состав заказа</h4>
                    <?php if ($items): ?>
                        <table class="order__items">
                            <thead>
                                <tr>
                                    <th>Товар</th>
                                    <th style="width:100px;">Цена</th>
                                    <th style="width:80px;">Кол-во</th>
                                    <th style="width:120px;">Сумма</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $it): ?>
                                    <tr>
                                        <td><?= ae($it['product_name']) ?></td>
                                        <td><?= number_format((float) $it['price'], 0, '.', ' ') ?> ₽</td>
                                        <td><?= (int) $it['qty'] ?></td>
                                        <td><?= number_format((float) $it['price'] * (int) $it['qty'], 0, '.', ' ') ?> ₽</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="admin-hint">Позиции не найдены.</p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($o['comment'])): ?>
                    <div class="order__block">
                        <h4 class="order__block-title">Комментарий покупателя</h4>
                        <p><?= nl2br(ae($o['comment'])) ?></p>
                    </div>
                <?php endif; ?>

                <div class="order__actions">
                    <a href="<?= BASE_URL ?>/admin/order-delete.php?id=<?= (int) $o['id'] ?>&csrf=<?= ae($_SESSION['csrf']) ?>"
                       class="admin-btn admin-btn--sm admin-btn--danger"
                       onclick="return confirm('Удалить заказ №<?= (int) $o['id'] ?>? Это действие необратимо.');">
                        Удалить заказ
                    </a>
                </div>

            </div>
        </details>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<?php require __DIR__ . '/admin-footer.php'; ?>