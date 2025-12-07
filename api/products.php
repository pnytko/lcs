<?php
/**
 * LuloCustoms Shop - Products API
 *
 * Endpoints:
 * - GET /api/products.php - Lista wszystkich produktów (public)
 * - GET /api/products.php?id=X - Pojedynczy produkt (public)
 * - POST /api/products.php - Dodaj produkt (admin only)
 * - PUT /api/products.php - Edytuj produkt (admin only)
 * - DELETE /api/products.php?id=X - Usuń produkt (admin only)
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

    case 'PUT':
        handlePut();
        break;

    case 'DELETE':
        handleDelete();
        break;

    default:
        jsonError('Method not allowed', 405);
}

/**
 * GET - Lista produktów lub pojedynczy produkt
 */
function handleGet() {
    $db = db();
    $id = $_GET['id'] ?? null;

    if ($id) {
        // Pojedynczy produkt
        $product = $db->selectOne(
            "SELECT * FROM products WHERE id = ?",
            [$id]
        );

        if (!$product) {
            jsonError('Product not found', 404);
        }

        jsonResponse([
            'success' => true,
            'product' => formatProduct($product)
        ]);
    } else {
        // Lista produktów
        // Tylko aktywne dla publicznych requestów, wszystkie dla admina
        $isAdmin = isAdminLoggedIn();

        $query = $isAdmin
            ? "SELECT * FROM products ORDER BY created_at DESC"
            : "SELECT * FROM products WHERE active = 1 ORDER BY created_at DESC";

        $products = $db->select($query);

        jsonResponse([
            'success' => true,
            'products' => array_map('formatProduct', $products)
        ]);
    }
}

/**
 * POST - Dodaj nowy produkt (tylko admin)
 */
function handlePost() {
    requireAuth();

    $db = db();

    // Sprawdź czy to multipart/form-data (upload zdjęcia)
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $stock = $_POST['stock'] ?? 0;
    $active = isset($_POST['active']) ? (int)$_POST['active'] : 1;

    // Walidacja
    if (empty($name)) {
        jsonError('Product name is required');
    }

    if ($price <= 0) {
        jsonError('Price must be greater than 0');
    }

    if ($stock < 0) {
        jsonError('Stock cannot be negative');
    }

    // Upload zdjęcia (jeśli jest)
    $imageUrl = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imageUrl = handleImageUpload($_FILES['image']);
        if (!$imageUrl) {
            jsonError('Image upload failed');
        }
    }

    // Dodaj do bazy
    $productId = $db->insert(
        "INSERT INTO products (name, description, price, image_url, stock, active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())",
        [
            sanitizeString($name),
            sanitizeString($description),
            $price,
            $imageUrl,
            $stock,
            $active
        ]
    );

    if (!$productId) {
        jsonError('Failed to create product', 500);
    }

    // Pobierz utworzony produkt
    $product = $db->selectOne("SELECT * FROM products WHERE id = ?", [$productId]);

    jsonResponse([
        'success' => true,
        'message' => 'Product created successfully',
        'product' => formatProduct($product)
    ], 201);
}

/**
 * PUT - Edytuj produkt (tylko admin)
 */
function handlePut() {
    requireAuth();

    $db = db();

    // Pobierz dane z JSON
    $input = getJSONInput();

    $id = $input['id'] ?? null;
    $name = $input['name'] ?? '';
    $description = $input['description'] ?? '';
    $price = $input['price'] ?? 0;
    $stock = $input['stock'] ?? 0;
    $active = $input['active'] ?? 1;

    // Walidacja
    if (!$id) {
        jsonError('Product ID is required');
    }

    // Sprawdź czy produkt istnieje
    $existing = $db->selectOne("SELECT * FROM products WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Product not found', 404);
    }

    if (empty($name)) {
        jsonError('Product name is required');
    }

    if ($price <= 0) {
        jsonError('Price must be greater than 0');
    }

    if ($stock < 0) {
        jsonError('Stock cannot be negative');
    }

    // Aktualizuj
    $updated = $db->update(
        "UPDATE products
         SET name = ?, description = ?, price = ?, stock = ?, active = ?, updated_at = NOW()
         WHERE id = ?",
        [
            sanitizeString($name),
            sanitizeString($description),
            $price,
            $stock,
            $active,
            $id
        ]
    );

    if ($updated === 0) {
        jsonError('Failed to update product or no changes made', 400);
    }

    // Pobierz zaktualizowany produkt
    $product = $db->selectOne("SELECT * FROM products WHERE id = ?", [$id]);

    jsonResponse([
        'success' => true,
        'message' => 'Product updated successfully',
        'product' => formatProduct($product)
    ]);
}

/**
 * DELETE - Usuń produkt (tylko admin)
 */
function handleDelete() {
    requireAuth();

    $db = db();
    $id = $_GET['id'] ?? null;

    if (!$id) {
        jsonError('Product ID is required');
    }

    // Sprawdź czy produkt istnieje
    $product = $db->selectOne("SELECT * FROM products WHERE id = ?", [$id]);
    if (!$product) {
        jsonError('Product not found', 404);
    }

    // Usuń zdjęcie (jeśli istnieje)
    if ($product['image_url']) {
        $imagePath = str_replace(UPLOAD_URL, UPLOAD_DIR, $product['image_url']);
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    // Usuń z bazy
    $deleted = $db->delete("DELETE FROM products WHERE id = ?", [$id]);

    if ($deleted === 0) {
        jsonError('Failed to delete product', 500);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Product deleted successfully'
    ]);
}

/**
 * Upload zdjęcia produktu
 */
function handleImageUpload($file) {
    // Walidacja
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowedTypes)) {
        jsonError('Invalid image type. Allowed: JPG, PNG, GIF, WEBP');
    }

    if ($file['size'] > $maxSize) {
        jsonError('Image too large. Max 5MB');
    }

    // Utwórz folder jeśli nie istnieje
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // Generuj unikalną nazwę
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('product_', true) . '.' . $extension;
    $filepath = UPLOAD_DIR . $filename;

    // Przenieś plik
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return null;
    }

    // Zwróć URL
    return UPLOAD_URL . $filename;
}

/**
 * Formatuje produkt (dodaje pełny URL do zdjęcia)
 */
function formatProduct($product) {
    return [
        'id' => (int)$product['id'],
        'name' => $product['name'],
        'description' => $product['description'],
        'price' => (float)$product['price'],
        'image_url' => $product['image_url'],
        'stock' => (int)$product['stock'],
        'active' => (bool)$product['active'],
        'created_at' => $product['created_at'],
        'updated_at' => $product['updated_at']
    ];
}
