<?php
/**
 * LuloCustoms Shop - PHP Backend Tests
 *
 * Uruchom: php tests/test-backend.php
 *
 * WYMAGANIA:
 * - Baza MySQL musi być skonfigurowana
 * - Dane w api/config.php muszą być poprawne
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Kolory w terminalu
function colorize($text, $color) {
    $colors = [
        'green' => "\033[0;32m",
        'red' => "\033[0;31m",
        'yellow' => "\033[1;33m",
        'blue' => "\033[0;34m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

class BackendTester {
    private $baseDir;
    private $passed = 0;
    private $failed = 0;
    private $testResults = [];

    public function __construct() {
        $this->baseDir = dirname(__DIR__);
    }

    public function run() {
        echo colorize("\n🧪 LuloCustoms Shop - Backend Tests\n", 'blue');
        echo colorize("=====================================\n\n", 'blue');

        $this->testDatabaseConnection();
        $this->testConfigFile();
        $this->testDatabaseClass();
        $this->testAuthFunctions();
        $this->testProductsDatabase();
        $this->testOrdersDatabase();

        $this->printSummary();
    }

    private function test($name, $callback) {
        try {
            $result = $callback();
            if ($result === true || $result === null) {
                $this->passed++;
                echo colorize("✓ ", 'green') . "$name\n";
                $this->testResults[] = ['name' => $name, 'status' => 'passed'];
            } else {
                $this->failed++;
                echo colorize("✗ ", 'red') . "$name\n";
                echo colorize("  Error: $result\n", 'red');
                $this->testResults[] = ['name' => $name, 'status' => 'failed', 'error' => $result];
            }
        } catch (Exception $e) {
            $this->failed++;
            echo colorize("✗ ", 'red') . "$name\n";
            echo colorize("  Exception: " . $e->getMessage() . "\n", 'red');
            $this->testResults[] = ['name' => $name, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    private function testDatabaseConnection() {
        echo colorize("\n📦 Database Connection Tests\n", 'yellow');
        echo str_repeat("-", 40) . "\n";

        $this->test("config.php file exists", function() {
            $path = $this->baseDir . '/api/config.php';
            return file_exists($path) ? true : "File not found: $path";
        });

        $this->test("database.php file exists", function() {
            $path = $this->baseDir . '/api/database.php';
            return file_exists($path) ? true : "File not found: $path";
        });

        $this->test("Database constants are defined", function() {
            require_once $this->baseDir . '/api/config.php';

            if (!defined('DB_HOST')) return "DB_HOST not defined";
            if (!defined('DB_NAME')) return "DB_NAME not defined";
            if (!defined('DB_USER')) return "DB_USER not defined";
            if (!defined('DB_PASS')) return "DB_PASS not defined";

            return true;
        });

        $this->test("Can connect to MySQL database", function() {
            require_once $this->baseDir . '/api/config.php';

            try {
                $pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                return true;
            } catch (PDOException $e) {
                return "Cannot connect: " . $e->getMessage();
            }
        });
    }

    private function testConfigFile() {
        echo colorize("\n⚙️  Configuration Tests\n", 'yellow');
        echo str_repeat("-", 40) . "\n";

        $this->test("Przelewy24 constants are defined", function() {
            require_once $this->baseDir . '/api/config.php';

            if (!defined('P24_MERCHANT_ID')) return "P24_MERCHANT_ID not defined";
            if (!defined('P24_POS_ID')) return "P24_POS_ID not defined";
            if (!defined('P24_CRC')) return "P24_CRC not defined";
            if (!defined('P24_TEST_MODE')) return "P24_TEST_MODE not defined";

            return true;
        });

        $this->test("Helper functions are defined", function() {
            require_once $this->baseDir . '/api/config.php';

            if (!function_exists('jsonResponse')) return "jsonResponse() not defined";
            if (!function_exists('jsonError')) return "jsonError() not defined";
            if (!function_exists('sanitizeString')) return "sanitizeString() not defined";
            if (!function_exists('isValidEmail')) return "isValidEmail() not defined";

            return true;
        });

        $this->test("Email validation works", function() {
            require_once $this->baseDir . '/api/config.php';

            if (!isValidEmail('test@example.com')) return "Valid email rejected";
            if (isValidEmail('invalid-email')) return "Invalid email accepted";

            return true;
        });

        $this->test("String sanitization works", function() {
            require_once $this->baseDir . '/api/config.php';

            $dirty = '<script>alert("XSS")</script>';
            $clean = sanitizeString($dirty);

            if (strpos($clean, '<script>') !== false) {
                return "Script tags not removed";
            }

            return true;
        });
    }

    private function testDatabaseClass() {
        echo colorize("\n🗄️  Database Class Tests\n", 'yellow');
        echo str_repeat("-", 40) . "\n";

        $this->test("Database class can be instantiated", function() {
            require_once $this->baseDir . '/api/config.php';
            require_once $this->baseDir . '/api/database.php';

            $db = Database::getInstance();
            return $db instanceof Database ? true : "Database instance is not valid";
        });

        $this->test("db() helper function works", function() {
            require_once $this->baseDir . '/api/config.php';
            require_once $this->baseDir . '/api/database.php';

            $db = db();
            return $db instanceof Database ? true : "db() helper failed";
        });

        $this->test("Can execute SELECT query", function() {
            require_once $this->baseDir . '/api/config.php';
            require_once $this->baseDir . '/api/database.php';

            $db = db();
            $result = $db->select("SELECT 1 as test");

            return (is_array($result) && count($result) > 0) ? true : "SELECT failed";
        });

        $this->test("Tables exist in database", function() {
            require_once $this->baseDir . '/api/config.php';
            require_once $this->baseDir . '/api/database.php';

            $db = db();
            $tables = ['admin_users', 'products', 'orders', 'order_items'];

            foreach ($tables as $table) {
                $result = $db->select("SHOW TABLES LIKE '$table'");
                if (empty($result)) {
                    return "Table '$table' does not exist";
                }
            }

            return true;
        });
    }

    private function testAuthFunctions() {
        echo colorize("\n🔐 Authentication Tests\n", 'yellow');
        echo str_repeat("-", 40) . "\n";

        $this->test("auth.php file exists", function() {
            $path = $this->baseDir . '/api/auth.php';
            return file_exists($path) ? true : "auth.php not found";
        });

        $this->test("Auth functions are defined", function() {
            // Nie możemy includować auth.php bo ma kod wykonawczy
            // Sprawdzamy tylko czy plik jest poprawny składniowo
            $path = $this->baseDir . '/api/auth.php';
            $content = file_get_contents($path);

            if (strpos($content, 'function handleLogin()') === false) {
                return "handleLogin() not found";
            }
            if (strpos($content, 'function handleLogout()') === false) {
                return "handleLogout() not found";
            }
            if (strpos($content, 'function isAdminLoggedIn()') === false) {
                return "isAdminLoggedIn() not found";
            }

            return true;
        });

        $this->test("Admin user exists in database", function() {
            require_once $this->baseDir . '/api/config.php';
            require_once $this->baseDir . '/api/database.php';

            $db = db();
            $admin = $db->selectOne("SELECT * FROM admin_users LIMIT 1");

            return $admin ? true : "No admin user found in database";
        });

        $this->test("Admin password is hashed", function() {
            require_once $this->baseDir . '/api/config.php';
            require_once $this->baseDir . '/api/database.php';

            $db = db();
            $admin = $db->selectOne("SELECT password_hash FROM admin_users LIMIT 1");

            if (!$admin) return "No admin user found";

            // Bcrypt hashes start with $2y$
            if (strpos($admin['password_hash'], '$2y$') !== 0) {
                return "Password is not bcrypt hashed";
            }

            return true;
        });
    }

    private function testProductsDatabase() {
        echo colorize("\n📦 Products Tests\n", 'yellow');
        echo str_repeat("-", 40) . "\n";

        $this->test("products.php file exists", function() {
            $path = $this->baseDir . '/api/products.php';
            return file_exists($path) ? true : "products.php not found";
        });

        $this->test("Can query products table", function() {
            require_once $this->baseDir . '/api/config.php';
            require_once $this->baseDir . '/api/database.php';

            $db = db();
            $products = $db->select("SELECT * FROM products");

            return is_array($products) ? true : "Cannot query products";
        });

        $this->test("Products have required columns", function() {
            require_once $this->baseDir . '/api/config.php';
            require_once $this->baseDir . '/api/database.php';

            $db = db();
            $product = $db->selectOne("SELECT * FROM products LIMIT 1");

            if (!$product) return "No products in database";

            $required = ['id', 'name', 'description', 'price', 'stock', 'active'];
            foreach ($required as $col) {
                if (!array_key_exists($col, $product)) {
                    return "Column '$col' missing";
                }
            }

            return true;
        });
    }

    private function testOrdersDatabase() {
        echo colorize("\n📋 Orders Tests\n", 'yellow');
        echo str_repeat("-", 40) . "\n";

        $this->test("orders.php file exists", function() {
            $path = $this->baseDir . '/api/orders.php';
            return file_exists($path) ? true : "orders.php not found";
        });

        $this->test("Can query orders table", function() {
            require_once $this->baseDir . '/api/config.php';
            require_once $this->baseDir . '/api/database.php';

            $db = db();
            $orders = $db->select("SELECT * FROM orders");

            return is_array($orders) ? true : "Cannot query orders";
        });

        $this->test("Orders table has correct structure", function() {
            require_once $this->baseDir . '/api/config.php';
            require_once $this->baseDir . '/api/database.php';

            $db = db();
            $columns = $db->select("DESCRIBE orders");

            $required = ['order_number', 'customer_email', 'total_price', 'payment_status'];
            $found = array_column($columns, 'Field');

            foreach ($required as $col) {
                if (!in_array($col, $found)) {
                    return "Column '$col' missing in orders table";
                }
            }

            return true;
        });
    }

    private function printSummary() {
        echo colorize("\n" . str_repeat("=", 40) . "\n", 'blue');
        echo colorize("📊 Test Summary\n", 'blue');
        echo colorize(str_repeat("=", 40) . "\n\n", 'blue');

        $total = $this->passed + $this->failed;
        $percentage = $total > 0 ? round(($this->passed / $total) * 100, 1) : 0;

        echo "Total tests: $total\n";
        echo colorize("Passed: {$this->passed}\n", 'green');

        if ($this->failed > 0) {
            echo colorize("Failed: {$this->failed}\n", 'red');
        }

        echo "Success rate: {$percentage}%\n\n";

        if ($this->failed === 0) {
            echo colorize("🎉 All tests passed!\n\n", 'green');
            exit(0);
        } else {
            echo colorize("❌ Some tests failed. Please fix the issues above.\n\n", 'red');
            exit(1);
        }
    }
}

// Run tests
$tester = new BackendTester();
$tester->run();
