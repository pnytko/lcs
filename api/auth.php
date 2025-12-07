<?php
/**
 * LuloCustoms Shop - Authentication API
 *
 * Endpoints:
 * - POST /api/auth.php?action=login
 * - POST /api/auth.php?action=logout
 * - GET /api/auth.php?action=check
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Pobierz action z query params
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;

    case 'logout':
        handleLogout();
        break;

    case 'check':
        handleCheck();
        break;

    default:
        jsonError('Invalid action', 400);
}

/**
 * POST /api/auth.php?action=login
 * Login admina
 */
function handleLogin() {
    requirePOST();

    // Pobierz dane z JSON body
    $input = getJSONInput();

    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';

    // Walidacja
    if (empty($email) || empty($password)) {
        jsonError('Email and password are required');
    }

    if (!isValidEmail($email)) {
        jsonError('Invalid email format');
    }

    // Rate limiting - max 5 prób na 15 minut
    if (!checkLoginAttempts($email)) {
        jsonError('Too many login attempts. Try again later.', 429);
    }

    // Szukaj admina w bazie
    $db = db();
    $admin = $db->selectOne(
        "SELECT * FROM admin_users WHERE email = ?",
        [$email]
    );

    // Sprawdź czy istnieje i hasło się zgadza
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        // Zaloguj nieudaną próbę
        recordLoginAttempt($email, false);
        jsonError('Invalid credentials', 401);
    }

    // Zaloguj udaną próbę
    recordLoginAttempt($email, true);

    // Utworz sesję
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['logged_in_at'] = time();

    // Regeneruj session ID (security)
    session_regenerate_id(true);

    jsonResponse([
        'success' => true,
        'message' => 'Login successful',
        'admin' => [
            'id' => $admin['id'],
            'email' => $admin['email']
        ]
    ]);
}

/**
 * POST /api/auth.php?action=logout
 * Wylogowanie admina
 */
function handleLogout() {
    requirePOST();

    // Wyczyść sesję
    $_SESSION = [];

    // Usuń cookie sesji
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Zniszcz sesję
    session_destroy();

    jsonResponse([
        'success' => true,
        'message' => 'Logout successful'
    ]);
}

/**
 * GET /api/auth.php?action=check
 * Sprawdza czy admin jest zalogowany
 */
function handleCheck() {
    requireGET();

    if (isAdminLoggedIn()) {
        jsonResponse([
            'success' => true,
            'logged_in' => true,
            'admin' => [
                'id' => $_SESSION['admin_id'],
                'email' => $_SESSION['admin_email']
            ]
        ]);
    } else {
        jsonResponse([
            'success' => true,
            'logged_in' => false
        ]);
    }
}

/**
 * Sprawdza czy admin jest zalogowany
 */
function isAdminLoggedIn() {
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['logged_in_at'])) {
        return false;
    }

    // Session timeout - 8 godzin
    $timeout = 8 * 60 * 60;
    if (time() - $_SESSION['logged_in_at'] > $timeout) {
        // Sesja wygasła
        session_destroy();
        return false;
    }

    return true;
}

/**
 * Wymaga zalogowania (używane w innych plikach API)
 */
function requireAuth() {
    if (!isAdminLoggedIn()) {
        jsonError('Unauthorized', 401);
    }
}

/**
 * Rate limiting - sprawdza próby logowania
 */
function checkLoginAttempts($email) {
    $key = 'login_attempts_' . md5($email);

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'first_attempt' => time()
        ];
    }

    $attempts = $_SESSION[$key];

    // Reset po 15 minutach
    if (time() - $attempts['first_attempt'] > 900) {
        $_SESSION[$key] = [
            'count' => 0,
            'first_attempt' => time()
        ];
        return true;
    }

    // Maksymalnie 5 prób
    return $attempts['count'] < 5;
}

/**
 * Zapisuje próbę logowania
 */
function recordLoginAttempt($email, $success) {
    $key = 'login_attempts_' . md5($email);

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'first_attempt' => time()
        ];
    }

    if ($success) {
        // Reset przy udanym logowaniu
        unset($_SESSION[$key]);
    } else {
        // Inkrementuj przy nieudanym
        $_SESSION[$key]['count']++;
    }
}
