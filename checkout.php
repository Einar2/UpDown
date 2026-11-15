<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/mailer.php';

$cart = cartItems($pdo);
if (!$cart['items']) {
    header('Location: ' . BASE_URL . '/cart.php');
    exit;
}

$errors = [];

// Значения по умолчанию для повторного заполнения формы
$form = [
    'name' => '',
    'phone' => '',
    'email' => '',
    'delivery' => 'pickup',
    'payment' => 'cash',
    'address' => '',
    'comment' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Собираем данные из формы
    $form['name'] = trim($_POST['name'] ?? '');
    $form['phone'] = trim($_POST['phone'] ?? '');
    $form['email'] = trim($_POST['email'] ?? '');
    $form['delivery'] = $_POST['delivery'] ?? 'pickup';
    $form['payment'] = $_POST['payment'] ?? 'cash';
    $form['address'] = trim($_POST['address'] ?? '');
    $form['comment'] = trim($_POST['comment'] ?? '');

    // ============ ВАЛИДАЦИЯ ============
    if ($form['name'] === '') {
        $errors['name'] = 'Укажите имя';
    }
    if ($form['phone'] === '') {
        $errors['phone'] = 'Укажите телефон';
    } elseif (!preg_match('/^[\d\s\+\-\(\)]{10,20}$/', $form['phone'])) {
        $errors['phone'] = 'Проверьте формат телефона';
    }
    if ($form['email'] === '') {
        $errors['email'] = 'Укажите email';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Проверьте формат email';
    }

    if (!in_array($form['delivery'], ['pickup', 'courier'], true)) {
        $form['delivery'] = 'pickup';
    }
    if ($form['delivery'] === 'courier' && $form['address'] === '') {
        $errors['address'] = 'Укажите адрес доставки';
    }

    // Разрешённые способы оплаты
    $allowedPayments = $form['delivery'] === 'pickup'
        ? ['cash', 'card']
        : ['cash', 'transfer', 'card'];

    if (!in_array($form['payment'], $allowedPayments, true)) {
        $form['payment'] = $allowedPayments[0];
    }

    // ============ СОХРАНЕНИЕ ЗАКАЗА ============
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Генерируем код подтверждения только для самовывоза
            $pickupCode = $form['delivery'] === 'pickup'
                ? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)
                : null;

            $stmt = $pdo->prepare("
                INSERT INTO orders
                    (customer_name, customer_phone, customer_email,
                     delivery_type, address, payment_type,
                     pickup_code, total, comment, status)
                VALUES
                    (:name, :phone, :email,
                     :delivery, :address, :payment,
                     :pickup_code, :total, :comment, 'new')
            ");
            $stmt->execute([
                'name' => $form['name'],
                'phone' => $form['phone'],
                'email' => $form['email'],
                'delivery' => $form['delivery'],
                'address' => $form['delivery'] === 'courier' ? $form['address'] : null,
                'payment' => $form['payment'],
                'pickup_code' => $pickupCode,
                'total' => $cart['total'],
                'comment' => $form['comment'] ?: null,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            // Позиции заказа
            $itemStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, price, qty)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($cart['items'] as $it) {
                $itemStmt->execute([
                    $orderId,
                    $it['id'],
                    $it['name'],
                    $it['price'],
                    $it['qty'],
                ]);
            }

            $pdo->commit();

            // ============ ОТПРАВКА ПИСЕМ ============
            $orderData = [
                'id' => $orderId,
                'name' => $form['name'],
                'phone' => $form['phone'],
                'email' => $form['email'],
                'delivery' => $form['delivery'],
                'address' => $form['address'],
                'payment' => $form['payment'],
                'pickup_code' => $pickupCode,
                'total' => $cart['total'],
                'items' => $cart['items'],
                'comment' => $form['comment'],
            ];

            // Покупателю
            sendOrderToCustomer($orderData);

            // Менеджеру / кассиру
            sendOrderToManager($orderData);

            // Очищаем корзину
            cartClear();

            // ============ ЕСЛИ ОНЛАЙН — РЕДИРЕКТ НА ОПЛАТУ ============
            header('Location: ' . BASE_URL . '/order-success.php?id=' . $orderId);
            exit;

        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Order error: ' . $e->getMessage());
            $errors['general'] = 'Не удалось оформить заказ. Попробуйте позже или свяжитесь с нами.';
        }
    }
}

$pageTitle = 'Оформление заказа';
require __DIR__ . '/includes/header.php';
?>

<h1 class="page-title">Оформление заказа</h1>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert--error"><?= e($errors['general']) ?></div>
<?php endif; ?>

<form class="checkout" method="post" novalidate>

    <div class="checkout__form">

        <!-- ============ КОНТАКТНЫЕ ДАННЫЕ ============ -->
        <section class="checkout__section">
            <h2 class="checkout__section-title">Контактные данные</h2>

            <div class="field">
                <label for="name">Имя*</label>
                <input type="text" id="name" name="name" required value="<?= e($form['name']) ?>"
                    placeholder="Как к вам обращаться">
                <?php if (!empty($errors['name'])): ?>
                    <small class="field__error"><?= e($errors['name']) ?></small>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="phone">Телефон*</label>
                <input type="tel" id="phone" name="phone" required value="<?= e($form['phone']) ?>"
                    placeholder="+7 (___) ___-__-__">
                <?php if (!empty($errors['phone'])): ?>
                    <small class="field__error"><?= e($errors['phone']) ?></small>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="email">Email*</label>
                <input type="email" id="email" name="email" required value="<?= e($form['email']) ?>"
                    placeholder="you@example.com">
                <small class="field__hint">На этот адрес придёт подтверждение заказа</small>
                <?php if (!empty($errors['email'])): ?>
                    <small class="field__error"><?= e($errors['email']) ?></small>
                <?php endif; ?>
            </div>
        </section>

        <!-- ============ СПОСОБ ПОЛУЧЕНИЯ ============ -->
        <section class="checkout__section">
            <h2 class="checkout__section-title">Способ получения</h2>

            <label class="field field--radio">
                <input type="radio" name="delivery" value="pickup" <?= $form['delivery'] === 'pickup' ? 'checked' : '' ?>>
                <span>
                    <b>Самовывоз</b><br>
                    <small>г. Ставрополь, ул. Голенева, 28/50</small>
                </span>
            </label>

            <label class="field field--radio">
                <input type="radio" name="delivery" value="courier" <?= $form['delivery'] === 'courier' ? 'checked' : '' ?>>
                <span>
                    <b>Доставка</b><br>
                    <small>Стоимость и сроки уточнит менеджер</small>
                </span>
            </label>

            <div class="field" id="addressField" style="<?= $form['delivery'] === 'courier' ? '' : 'display:none' ?>">
                <label for="address">Адрес доставки*</label>
                <input type="text" id="address" name="address" value="<?= e($form['address']) ?>"
                    placeholder="Город, улица, дом, квартира">
                <?php if (!empty($errors['address'])): ?>
                    <small class="field__error"><?= e($errors['address']) ?></small>
                <?php endif; ?>
            </div>
        </section>

        <!-- ============ СПОСОБ ОПЛАТЫ ============ -->
        <section class="checkout__section">
            <h2 class="checkout__section-title">Способ оплаты</h2>



            <!-- Для самовывоза -->
            <label class="field field--radio js-payment-pickup"
                style="<?= $form['delivery'] === 'pickup' ? '' : 'display:none' ?>">
                <input type="radio" name="payment" value="cash" <?= $form['payment'] === 'cash' ? 'checked' : '' ?>>
                <span>
                    <b>При получении</b><br>
                    <small>Оплата наличными или картой в пункте выдачи</small>
                </span>
            </label>

            <!-- Для доставки: наличные -->
            <label class="field field--radio js-payment-courier"
                style="<?= $form['delivery'] === 'courier' ? '' : 'display:none' ?>">
                <input type="radio" name="payment" value="cash" <?= $form['payment'] === 'cash' && $form['delivery'] === 'courier' ? 'checked' : '' ?>>
                <span>
                    <b>При получении — наличными</b><br>
                    <small>Курьеру при доставке</small>
                </span>
            </label>

            <!-- Для доставки: перевод -->
            <label class="field field--radio js-payment-courier"
                style="<?= $form['delivery'] === 'courier' ? '' : 'display:none' ?>">
                <input type="radio" name="payment" value="transfer" <?= $form['payment'] === 'transfer' ? 'checked' : '' ?>>
                <span>
                    <b>При получении — переводом</b><br>
                    
                </span>
            </label>
        </section>

        <!-- ============ КОММЕНТАРИЙ ============ -->
        <section class="checkout__section">
            <h2 class="checkout__section-title">Комментарий к заказу</h2>
            <div class="field">
                <textarea name="comment" rows="3"
                    placeholder="Необязательно. Например, удобное время доставки или самовывоза"><?= e($form['comment']) ?></textarea>
            </div>
        </section>

        <button type="submit" class="btn btn--cart btn--cart-big">
            Оформить заказ
        </button>

        <p class="checkout__note">
            Оформляя заказ, вы соглашаетесь с
            <a href="<?= BASE_URL ?>/agreement.php" target="_blank">условиями оферты</a>
            и
            <a href="<?= BASE_URL ?>/privacy.php" target="_blank">политикой конфиденциальности</a>.
        </p>
    </div>

    <!-- ============ СВОДКА ПО ЗАКАЗУ ============ -->
    <aside class="cart-summary">
        <div class="cart-summary__box">
            <h3 class="cart-summary__title">Ваш заказ</h3>

            <ul class="cart-summary__items">
                <?php foreach ($cart['items'] as $it): ?>
                    <li class="cart-summary__item">
                        <span class="cart-summary__item-name">
                            <?= e($it['name']) ?> × <?= (int) $it['qty'] ?>
                        </span>
                        <span class="cart-summary__item-sum">
                            <?= formatPrice($it['sum']) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="cart-summary__row">
                <span>Итого:</span>
                <b><?= formatPrice($cart['total']) ?></b>
            </div>
        </div>
    </aside>

</form>

<script>
    // Показ адреса только при доставке
    document.querySelectorAll('input[name="delivery"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var isCourier = document.querySelector('input[name="delivery"]:checked').value === 'courier';

            document.getElementById('addressField').style.display = isCourier ? '' : 'none';

            document.querySelectorAll('.js-payment-pickup').forEach(function (el) {
                el.style.display = isCourier ? 'none' : '';
            });
            document.querySelectorAll('.js-payment-courier').forEach(function (el) {
                el.style.display = isCourier ? '' : 'none';
            });

            // Сброс выбора оплаты на подходящий
            var firstVisible = document.querySelector('.js-payment-' + (isCourier ? 'courier' : 'pickup') + ' input');
            if (firstVisible && !document.querySelector('.js-payment-' + (isCourier ? 'courier' : 'pickup') + ' input:checked')) {
                firstVisible.checked = true;
            }
        });
    });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>