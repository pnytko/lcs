<?php
/**
 * LuloCustoms Shop - Payment API (Przelewy24)
 *
 * Endpoints:
 * - POST /api/payment.php?action=init - Inicjalizacja płatności
 * - POST /api/payment.php?action=verify - Webhook weryfikacji (P24)
 * - GET /api/payment.php?action=status&session_id=X - Sprawdź status płatności
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'init':
        handleInit();
        break;

    case 'verify':
        handleVerify();
        break;

    case 'status':
        handleStatus();
        break;

    default:
        jsonError('Invalid action', 400);
}

/**
 * POST /api/payment.php?action=init
 * Inicjalizuje płatność w Przelewy24
 */
function handleInit() {
    requirePOST();

    $db = db();
    $input = getJSONInput();

    $orderId = $input['order_id'] ?? null;

    if (!$orderId) {
        jsonError('Order ID is required');
    }

    // Pobierz zamówienie
    $order = $db->selectOne("SELECT * FROM orders WHERE id = ?", [$orderId]);

    if (!$order) {
        jsonError('Order not found', 404);
    }

    if ($order['payment_status'] === 'paid') {
        jsonError('Order already paid');
    }

    // Generuj session ID
    $sessionId = uniqid('p24_', true);

    // Przygotuj dane dla P24
    $p24Data = [
        'merchantId' => (int)P24_MERCHANT_ID,
        'posId' => (int)P24_POS_ID,
        'sessionId' => $sessionId,
        'amount' => (int)($order['total_price'] * 100), // grosz
        'currency' => 'PLN',
        'description' => 'Zamówienie ' . $order['order_number'],
        'email' => $order['customer_email'],
        'client' => $order['customer_name'],
        'country' => 'PL',
        'language' => 'pl',
        'urlReturn' => P24_RETURN_URL . '?session_id=' . $sessionId,
        'urlStatus' => P24_STATUS_URL,
        'encoding' => 'UTF-8'
    ];

    // Oblicz CRC (sign)
    $p24Data['sign'] = calculateP24Sign($p24Data);

    // Wyślij request do P24
    $response = callP24API('/transaction/register', $p24Data);

    if (!$response || !isset($response['data']['token'])) {
        logError('P24 registration failed', ['response' => $response]);
        jsonError('Payment initialization failed', 500);
    }

    $token = $response['data']['token'];

    // Zapisz session_id do zamówienia
    $db->update(
        "UPDATE orders SET p24_session_id = ? WHERE id = ?",
        [$sessionId, $orderId]
    );

    // Zwróć URL do przekierowania
    $redirectUrl = P24_GATEWAY_URL . '/' . $token;

    jsonResponse([
        'success' => true,
        'redirect_url' => $redirectUrl,
        'session_id' => $sessionId,
        'token' => $token
    ]);
}

/**
 * POST /api/payment.php?action=verify
 * Webhook - weryfikacja płatności z P24
 */
function handleVerify() {
    requirePOST();

    $db = db();

    // P24 wysyła dane jako POST params (nie JSON)
    $merchantId = $_POST['merchantId'] ?? null;
    $posId = $_POST['posId'] ?? null;
    $sessionId = $_POST['sessionId'] ?? null;
    $amount = $_POST['amount'] ?? null;
    $originAmount = $_POST['originAmount'] ?? null;
    $currency = $_POST['currency'] ?? null;
    $orderId = $_POST['orderId'] ?? null;
    $methodId = $_POST['methodId'] ?? null;
    $statement = $_POST['statement'] ?? null;
    $sign = $_POST['sign'] ?? null;

    // Walidacja
    if (!$sessionId || !$orderId || !$sign) {
        logError('P24 verify - missing params', $_POST);
        jsonError('Missing required parameters');
    }

    // Sprawdź sign (CRC)
    $expectedSign = hash('sha384', json_encode([
        'sessionId' => $sessionId,
        'orderId' => $orderId,
        'amount' => $amount,
        'currency' => $currency,
        'crc' => P24_CRC
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    if ($sign !== $expectedSign) {
        logError('P24 verify - invalid sign', [
            'received' => $sign,
            'expected' => $expectedSign
        ]);
        jsonError('Invalid signature', 403);
    }

    // Znajdź zamówienie po session_id
    $order = $db->selectOne(
        "SELECT * FROM orders WHERE p24_session_id = ?",
        [$sessionId]
    );

    if (!$order) {
        logError('P24 verify - order not found', ['session_id' => $sessionId]);
        jsonError('Order not found', 404);
    }

    // Weryfikuj płatność w API P24
    $verifyData = [
        'merchantId' => (int)P24_MERCHANT_ID,
        'posId' => (int)P24_POS_ID,
        'sessionId' => $sessionId,
        'amount' => (int)$amount,
        'currency' => $currency,
        'orderId' => (int)$orderId
    ];

    $verifyData['sign'] = calculateP24Sign($verifyData);

    $response = callP24API('/transaction/verify', $verifyData);

    if (!$response || $response['data']['status'] !== 'success') {
        logError('P24 verification failed', ['response' => $response]);

        // Oznacz jako failed
        $db->update(
            "UPDATE orders SET payment_status = 'failed', p24_transaction_id = ? WHERE id = ?",
            [$orderId, $order['id']]
        );

        jsonError('Payment verification failed', 400);
    }

    // Płatność zweryfikowana - zaktualizuj zamówienie
    $db->update(
        "UPDATE orders SET payment_status = 'paid', p24_transaction_id = ? WHERE id = ?",
        [$orderId, $order['id']]
    );

    // Zaktualizuj stock produktów
    $items = $db->select(
        "SELECT * FROM order_items WHERE order_id = ?",
        [$order['id']]
    );

    foreach ($items as $item) {
        $db->update(
            "UPDATE products SET stock = stock - ? WHERE id = ?",
            [$item['quantity'], $item['product_id']]
        );
    }

    // Tutaj możesz wysłać email potwierdzający...
    // sendOrderConfirmationEmail($order);

    jsonResponse([
        'success' => true,
        'message' => 'Payment verified successfully'
    ]);
}

/**
 * GET /api/payment.php?action=status&session_id=X
 * Sprawdza status płatności
 */
function handleStatus() {
    requireGET();

    $db = db();
    $sessionId = $_GET['session_id'] ?? null;

    if (!$sessionId) {
        jsonError('Session ID is required');
    }

    // Znajdź zamówienie
    $order = $db->selectOne(
        "SELECT * FROM orders WHERE p24_session_id = ?",
        [$sessionId]
    );

    if (!$order) {
        jsonError('Order not found', 404);
    }

    jsonResponse([
        'success' => true,
        'status' => $order['payment_status'],
        'order_number' => $order['order_number'],
        'total_price' => (float)$order['total_price']
    ]);
}

/**
 * Oblicza sign (CRC) dla Przelewy24
 */
function calculateP24Sign($data) {
    $signData = [
        'sessionId' => $data['sessionId'],
        'merchantId' => $data['merchantId'] ?? P24_MERCHANT_ID,
        'amount' => $data['amount'],
        'currency' => $data['currency'] ?? 'PLN',
        'crc' => P24_CRC
    ];

    $signString = json_encode($signData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return hash('sha384', $signString);
}

/**
 * Wysyła request do API Przelewy24
 */
function callP24API($endpoint, $data) {
    $url = P24_API_URL . $endpoint;

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode(P24_POS_ID . ':' . P24_API_KEY)
        ],
        CURLOPT_SSL_VERIFYPEER => !P24_TEST_MODE
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($error) {
        logError('P24 API call failed', [
            'endpoint' => $endpoint,
            'error' => $error
        ]);
        return null;
    }

    $decoded = json_decode($response, true);

    if ($httpCode !== 200 && $httpCode !== 201) {
        logError('P24 API returned error', [
            'endpoint' => $endpoint,
            'http_code' => $httpCode,
            'response' => $decoded
        ]);
        return null;
    }

    return $decoded;
}
