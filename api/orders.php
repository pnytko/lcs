<?php
/**
 * LuloCustoms Shop - Orders API
 *
 * Endpoints:
 * - POST /api/orders.php - Utwórz nowe zamówienie (public)
 * - GET /api/orders.php - Lista wszystkich zamówień (admin only)
 * - GET /api/orders.php?id=X - Pojedyncze zamówienie (admin only)
 * - GET /api/orders.php?order_number=XXX - Sprawdź status zamówienia (public)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet();
        break;

    case 'POST':
        handlePost();
        break;

    default:
        jsonError('Method not allowed', 405);
}

/**
 * GET - Lista zamówień lub pojedyncze zamówienie
 */
function handleGet() {
    $db = db();
    $id = $_GET['id'] ?? null;
    $orderNumber = $_GET['order_number'] ?? null;

    // Sprawdzenie zamówienia po order_number (public)
    if ($orderNumber) {
        $order = $db->selectOne(
            "SELECT * FROM orders WHERE order_number = ?",
            [$orderNumber]
        );

        if (!$order) {
            jsonError('Order not found', 404);
        }

        jsonResponse([
            'success' => true,
            'order' => formatOrderPublic($order)
        ]);
        return;
    }

    // Reszta wymaga uwierzytelnienia
    requireAuth();

    if ($id) {
        // Pojedyncze zamówienie (admin)
        $order = $db->selectOne(
            "SELECT * FROM orders WHERE id = ?",
            [$id]
        );

        if (!$order) {
            jsonError('Order not found', 404);
        }

        // Pobierz pozycje zamówienia
        $items = $db->select(
            "SELECT * FROM order_items WHERE order_id = ?",
            [$id]
        );

        $order['items'] = array_map('formatOrderItem', $items);

        jsonResponse([
            'success' => true,
            'order' => formatOrder($order)
        ]);
    } else {
        // Lista wszystkich zamówień (admin)
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        $status = $_GET['status'] ?? null;

        $query = "SELECT * FROM orders";
        $params = [];

        if ($status) {
            $query .= " WHERE payment_status = ?";
            $params[] = $status;
        }

        $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;

        $orders = $db->select($query, $params);

        // Pobierz całkowitą liczbę zamówień
        $countQuery = "SELECT COUNT(*) as count FROM orders";
        if ($status) {
            $countQuery .= " WHERE payment_status = ?";
            $count = $db->selectOne($countQuery, [$status]);
        } else {
            $count = $db->selectOne($countQuery);
        }

        jsonResponse([
            'success' => true,
            'orders' => array_map('formatOrder', $orders),
            'total' => (int)$count['count'],
            'limit' => (int)$limit,
            'offset' => (int)$offset
        ]);
    }
}

/**
 * POST - Utwórz nowe zamówienie
 */
function handlePost() {
    $db = db();

    // Pobierz dane z JSON
    $input = getJSONInput();

    $customerName = $input['customer_name'] ?? '';
    $customerEmail = $input['customer_email'] ?? '';
    $customerPhone = $input['customer_phone'] ?? '';
    $customerAddress = $input['customer_address'] ?? '';
    $items = $input['items'] ?? [];

    // Walidacja
    if (empty($customerName)) {
        jsonError('Customer name is required');
    }

    if (empty($customerEmail) || !isValidEmail($customerEmail)) {
        jsonError('Valid email is required');
    }

    if (empty($customerPhone)) {
        jsonError('Phone number is required');
    }

    if (empty($customerAddress)) {
        jsonError('Address is required');
    }

    if (empty($items) || !is_array($items)) {
        jsonError('Order must contain at least one item');
    }

    // Walidacja produktów i oblicz sumę
    $totalPrice = 0;
    $validatedItems = [];

    foreach ($items as $item) {
        $productId = $item['product_id'] ?? null;
        $quantity = $item['quantity'] ?? 0;

        if (!$productId || $quantity <= 0) {
            jsonError('Invalid item data');
        }

        // Pobierz produkt z bazy
        $product = $db->selectOne(
            "SELECT * FROM products WHERE id = ? AND active = 1",
            [$productId]
        );

        if (!$product) {
            jsonError("Product with ID {$productId} not found or inactive");
        }

        // Sprawdź stock
        if ($product['stock'] < $quantity) {
            jsonError("Insufficient stock for product: {$product['name']}");
        }

        $itemPrice = $product['price'] * $quantity;
        $totalPrice += $itemPrice;

        $validatedItems[] = [
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'product_price' => $product['price'],
            'quantity' => $quantity
        ];
    }

    // Rozpocznij transakcję
    $db->beginTransaction();

    try {
        // Generuj unikalny numer zamówienia
        $orderNumber = generateOrderNumber();

        // Sprawdź czy nie istnieje (bardzo mała szansa, ale...)
        while ($db->exists('orders', 'order_number', $orderNumber)) {
            $orderNumber = generateOrderNumber();
        }

        // Utworz zamówienie
        $orderId = $db->insert(
            "INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, customer_address, total_price, payment_status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
            [
                $orderNumber,
                sanitizeString($customerName),
                sanitizeString($customerEmail),
                sanitizeString($customerPhone),
                sanitizeString($customerAddress),
                $totalPrice
            ]
        );

        if (!$orderId) {
            throw new Exception('Failed to create order');
        }

        // Dodaj pozycje zamówienia
        foreach ($validatedItems as $item) {
            $itemId = $db->insert(
                "INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    $orderId,
                    $item['product_id'],
                    $item['product_name'],
                    $item['product_price'],
                    $item['quantity']
                ]
            );

            if (!$itemId) {
                throw new Exception('Failed to create order item');
            }

            // Aktualizuj stock (opcjonalnie - można zrobić to dopiero po płatności)
            // $db->update(
            //     "UPDATE products SET stock = stock - ? WHERE id = ?",
            //     [$item['quantity'], $item['product_id']]
            // );
        }

        // Zatwierdź transakcję
        $db->commit();

        // Pobierz utworzone zamówienie
        $order = $db->selectOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
        $orderItems = $db->select("SELECT * FROM order_items WHERE order_id = ?", [$orderId]);

        $order['items'] = array_map('formatOrderItem', $orderItems);

        jsonResponse([
            'success' => true,
            'message' => 'Order created successfully',
            'order' => formatOrder($order)
        ], 201);

    } catch (Exception $e) {
        // Rollback przy błędzie
        $db->rollback();
        logError('Order creation failed', ['error' => $e->getMessage()]);
        jsonError('Failed to create order', 500);
    }
}

/**
 * Formatuje zamówienie (pełne dane dla admina)
 */
function formatOrder($order) {
    return [
        'id' => (int)$order['id'],
        'order_number' => $order['order_number'],
        'customer_name' => $order['customer_name'],
        'customer_email' => $order['customer_email'],
        'customer_phone' => $order['customer_phone'],
        'customer_address' => $order['customer_address'],
        'total_price' => (float)$order['total_price'],
        'payment_status' => $order['payment_status'],
        'p24_transaction_id' => $order['p24_transaction_id'],
        'p24_session_id' => $order['p24_session_id'],
        'created_at' => $order['created_at'],
        'items' => $order['items'] ?? []
    ];
}

/**
 * Formatuje zamówienie (public - ograniczone dane)
 */
function formatOrderPublic($order) {
    return [
        'order_number' => $order['order_number'],
        'total_price' => (float)$order['total_price'],
        'payment_status' => $order['payment_status'],
        'created_at' => $order['created_at']
    ];
}

/**
 * Formatuje pozycję zamówienia
 */
function formatOrderItem($item) {
    return [
        'id' => (int)$item['id'],
        'product_id' => (int)$item['product_id'],
        'product_name' => $item['product_name'],
        'product_price' => (float)$item['product_price'],
        'quantity' => (int)$item['quantity']
    ];
}
