<?php

/**
 * Адреса для писем
 */
const MAIL_FROM      = 'noreply@ca413618.tw1.ru';  // ← ЗАМЕНИ на свой ящик
const MAIL_MANAGER   = 'sidor.sasha2000@gmail.com'; // ← куда слать уведомления менеджеру
const MAIL_CASHIER   = 'sidor.sasha2000@gmail.com'; // ← куда слать уведомления кассиру
const MAIL_FROM_NAME = 'Вверх — Вниз';
const SITE_URL       = 'https://ca413618.tw1.ru';

/**
 * Отправка письма покупателю
 */
function sendOrderToCustomer(array $o): bool
{
    $subject = 'Заказ №' . $o['id'] . ' оформлен';

    $body = "Здравствуйте, {$o['name']}!\n\n";
    $body .= "Спасибо за заказ в магазине «Вверх — Вниз».\n\n";
    $body .= "Номер заказа: {$o['id']}\n";
    $body .= "Сумма: " . number_format($o['total'], 0, '.', ' ') . " ₽\n\n";

    $body .= "Состав заказа:\n";
    foreach ($o['items'] as $it) {
        $body .= "  • {$it['name']} × {$it['qty']} — " . number_format($it['sum'], 0, '.', ' ') . " ₽\n";
    }

    $body .= "\n";

    if ($o['delivery'] === 'pickup') {
        $body .= "Способ получения: Самовывоз\n";
        $body .= "Адрес пункта выдачи: г. Ставрополь, ул. Голенева, 28/50\n\n";

        if (!empty($o['pickup_code'])) {
            $body .= "ВАШ КОД ПОДТВЕРЖДЕНИЯ: {$o['pickup_code']}\n";
            $body .= "Назовите этот код сотруднику пункта выдачи для получения заказа.\n\n";
        }
    } else {
        $body .= "Способ получения: Доставка\n";
        $body .= "Адрес: {$o['address']}\n\n";
    }

    $payments = [
        'cash' => 'Наличными при получении',
        'transfer' => 'Переводом',
        'card' => 'Картой при получении',
    ];
    $body .= "Способ оплаты: " . ($payments[$o['payment']] ?? $o['payment']) . "\n\n";

    $body .= "Если у вас есть вопросы — ответьте на это письмо.\n\n";
    $body .= "С уважением,\nМагазин «Вверх — Вниз»\n" . SITE_URL;

    return sendMail($o['email'], $subject, $body);
}

/**
 * Отправка письма менеджеру (и кассиру, если самовывоз)
 */
function sendOrderToManager(array $o): bool
{
    $subject = 'Новый заказ №' . $o['id'];

    $body = "Новый заказ на сайте.\n\n";
    $body .= "Номер: {$o['id']}\n";
    $body .= "Дата: " . date('d.m.Y H:i') . "\n\n";

    $body .= "Покупатель:\n";
    $body .= "  Имя: {$o['name']}\n";
    $body .= "  Телефон: {$o['phone']}\n";
    $body .= "  Email: {$o['email']}\n\n";

    if ($o['delivery'] === 'pickup') {
        $body .= "Способ получения: САМОВЫВОЗ\n";
        $body .= "Адрес: г. Ставрополь, ул. Голенева, 28/50\n";
        if (!empty($o['pickup_code'])) {
            $body .= "КОД ПОДТВЕРЖДЕНИЯ: {$o['pickup_code']}\n";
        }
        $body .= "\n";
    } else {
        $body .= "Способ получения: ДОСТАВКА\n";
        $body .= "Адрес: {$o['address']}\n\n";
    }

    $payments = [
        'cash' => 'Наличными при получении',
        'transfer' => 'Переводом по реквизитам',
        'card' => 'Картой при получении (терминал)',
    ];
    $body .= "Способ оплаты: " . ($payments[$o['payment']] ?? $o['payment']) . "\n\n";

    $body .= "Состав заказа:\n";
    foreach ($o['items'] as $it) {
        $body .= "  • {$it['name']} × {$it['qty']} — " . number_format($it['sum'], 0, '.', ' ') . " ₽\n";
    }
    $body .= "\nИтого: " . number_format($o['total'], 0, '.', ' ') . " ₽\n";

    if (!empty($o['comment'])) {
        $body .= "\nКомментарий покупателя:\n{$o['comment']}\n";
    }

    $ok = sendMail(MAIL_MANAGER, $subject, $body);

    if ($o['delivery'] === 'pickup') {
        sendMail(MAIL_CASHIER, '[Самовывоз] ' . $subject, $body);
    }

    return $ok;
}

/**
 * Отправка письма через SMTP с SSL (порт 465).
 */
function sendMail(string $to, string $subject, string $body): bool
{
    // ============================================================
    // НАСТРОЙКИ SMTP — ЗАМЕНИ НА СВОИ
    // ============================================================
    $smtpHost = 'smtp.timeweb.ru';
    $smtpPort = 465;                                // 465 = SSL, 587 = TLS
    $smtpUser = 'noreply@ca413618.tw1.ru';          // ← твой ящик
    $smtpPass = 'ЗДЕСЬ_ПАРОЛЬ_ОТ_ЯЩИКА';            // ← пароль от ящика
    // ============================================================

    $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $headers = [
        'From'         => sprintf('%s <%s>', MAIL_FROM_NAME, $smtpUser),
        'To'           => $to,
        'Subject'      => $subjectEncoded,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
    ];

    $headerStr = '';
    foreach ($headers as $key => $value) {
        $headerStr .= "$key: $value\r\n";
    }

    $context = stream_context_create([
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);

    $socket = @stream_socket_client(
        'ssl://' . $smtpHost . ':' . $smtpPort,
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        error_log("SMTP connect failed: $errstr ($errno)");
        return false;
    }

    // 220 — приветствие
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '220') {
        error_log("SMTP greeting error: $response");
        fclose($socket);
        return false;
    }

    // HELO
    $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
    fwrite($socket, "HELO $hostname\r\n");
    fgets($socket, 515);

    // AUTH LOGIN
    fwrite($socket, "AUTH LOGIN\r\n");
    fgets($socket, 515);

    fwrite($socket, base64_encode($smtpUser) . "\r\n");
    fgets($socket, 515);

    fwrite($socket, base64_encode($smtpPass) . "\r\n");
    $authResponse = fgets($socket, 515);

    if (substr($authResponse, 0, 3) !== '235') {
        error_log("SMTP auth failed: $authResponse");
        fclose($socket);
        return false;
    }

    // MAIL FROM
    fwrite($socket, "MAIL FROM: <$smtpUser>\r\n");
    fgets($socket, 515);

    // RCPT TO
    fwrite($socket, "RCPT TO: <$to>\r\n");
    fgets($socket, 515);

    // DATA
    fwrite($socket, "DATA\r\n");
    fgets($socket, 515);

    // Тело письма (точка на отдельной строке — конец данных)
    fwrite($socket, $headerStr . "\r\n" . $body . "\r\n.\r\n");
    $dataResponse = fgets($socket, 515);

    if (substr($dataResponse, 0, 3) !== '250') {
        error_log("SMTP data error: $dataResponse");
    }

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return true;
}