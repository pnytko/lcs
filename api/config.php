<?php
/**
 * LuloCustoms Shop - Configuration
 *
 * WAŻNE: Po wdrożeniu na hosting ZMIEŃ te wartości!
 * - Dane MySQL z phpMyAdmin
 * - Klucze Przelewy24 z panelu P24
 * - Ustaw P24_TEST_MODE na false po testach
 */

// ==========================================
// KONFIGURACJA BAZY DANYCH
// ==========================================
// ZMIEŃ te wartości na dane z Twojego hostingu!

define('DB_HOST', 'localhost');              // Zazwyczaj 'localhost'
define('DB_NAME', 'lulocustoms_shop');       // Nazwa bazy z phpMyAdmin
define('DB_USER', 'root');                   // User z phpMyAdmin
define('DB_PASS', '');                       // Hasło z phpMyAdmin

// ==========================================
// KONFIGURACJA PRZELEWY24
// ==========================================
// Zarejestruj się na https://przelewy24.pl
// Pobierz dane z panelu: Moje dane → Sklepy i Stanowiska

define('P24_MERCHANT_ID', 'xxxxx');          // ID Sprzedawcy
define('P24_POS_ID', 'xxxxx');               // ID Stanowiska (zazwyczaj = Merchant ID)
define('P24_CRC', 'xxxxxxxxxxxxxxxx');       // Klucz do raportów (CRC)
define('P24_API_KEY', '');                   // API Key (opcjonalnie)
define('P24_TEST_MODE', true);               // true = sandbox, false = produkcja

// URL API Przelewy24
define('P24_API_URL', P24_TEST_MODE
    ? 'https://sandbox.przelewy24.pl/api/v1'
    : 'https://secure.przelewy24.pl/api/v1'
);

// URL dla przekierowań
define('P24_GATEWAY_URL', P24_TEST_MODE
    ? 'https://sandbox.przelewy24.pl/trnRequest'
    : 'https://secure.przelewy24.pl/trnRequest'
);

// ==========================================
// KONFIGURACJA APLIKACJI
// ==========================================

// URL Twojego sklepu (BEZ trailing slash)
define('SITE_URL', 'https://sklep.lulocustoms.pl');

// URL powrotu po płatności
define('P24_RETURN_URL', SITE_URL . '/potwierdzenie');
define('P24_STATUS_URL', SITE_URL . '/api/payment.php?action=verify');

// Ścieżki do folderów
define('UPLOAD_DIR', __DIR__ . '/../uploads/products/');
define('UPLOAD_URL', SITE_URL . '/uploads/products/');

// ==========================================
// BEZPIECZEŃSTWO
// ==========================================

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);        // HTTPS only
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);

// Error reporting (WYŁĄCZ na produkcji!)
if (P24_TEST_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Europe/Warsaw');

// CORS headers - ZMIEŃ na właściwą domenę!
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Content type JSON
header('Content-Type: application/json; charset=utf-8');

// ==========================================
// FUNKCJE POMOCNICZE
// ==========================================

/**
 * Zwraca JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Zwraca błąd JSON
 */
function jsonError($message, $statusCode = 400) {
    jsonResponse([
        'success' => false,
        'error' => $message
    ], $statusCode);
}

/**
 * Sprawdza czy request to POST
 */
function requirePOST() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Method not allowed', 405);
    }
}

/**
 * Sprawdza czy request to GET
 */
function requireGET() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonError('Method not allowed', 405);
    }
}

/**
 * Pobiera dane JSON z body
 */
function getJSONInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonError('Invalid JSON');
    }

    return $data;
}

/**
 * Sanitize string (XSS protection)
 */
function sanitizeString($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

/**
 * Walidacja email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generuje unikalny numer zamówienia
 */
function generateOrderNumber() {
    return 'ORD-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

/**
 * Log errors (dla debugowania)
 */
function logError($message, $context = []) {
    if (P24_TEST_MODE) {
        error_log(date('[Y-m-d H:i:s] ') . $message . ' ' . json_encode($context));
    }
}

// ==========================================
// INICJALIZACJA SESJI
// ==========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
