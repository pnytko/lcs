<?php
/**
 * LuloCustoms Shop - Database Connection
 *
 * PDO connection z prepared statements (SQL injection protection)
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $pdo;

    /**
     * Singleton pattern - jedna instancja połączenia
     */
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];

            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        } catch (PDOException $e) {
            logError('Database connection failed', ['error' => $e->getMessage()]);
            jsonError('Database connection failed', 500);
        }
    }

    /**
     * Pobiera instancję Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Pobiera PDO connection
     */
    public function getConnection() {
        return $this->pdo;
    }

    /**
     * SELECT - pobiera wiele wierszy
     *
     * @param string $query SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return array Array of rows
     */
    public function select($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            logError('SELECT query failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * SELECT - pobiera jeden wiersz
     *
     * @param string $query SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return array|null Single row or null
     */
    public function selectOne($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            logError('SELECT ONE query failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * INSERT - dodaje wiersz
     *
     * @param string $query SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return int|false Last insert ID or false on failure
     */
    public function insert($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            $result = $stmt->execute($params);

            if ($result) {
                return $this->pdo->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            logError('INSERT query failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * UPDATE - aktualizuje wiersz(e)
     *
     * @param string $query SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return int Number of affected rows
     */
    public function update($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            logError('UPDATE query failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * DELETE - usuwa wiersz(e)
     *
     * @param string $query SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return int Number of affected rows
     */
    public function delete($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            logError('DELETE query failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Execute - wykonuje dowolne zapytanie
     *
     * @param string $query SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return bool Success or failure
     */
    public function execute($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            logError('EXECUTE query failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * BEGIN TRANSACTION
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * COMMIT TRANSACTION
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * ROLLBACK TRANSACTION
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }

    /**
     * Sprawdza czy rekord istnieje
     */
    public function exists($table, $column, $value) {
        $query = "SELECT COUNT(*) as count FROM `{$table}` WHERE `{$column}` = ?";
        $result = $this->selectOne($query, [$value]);
        return $result && $result['count'] > 0;
    }
}

/**
 * Helper function - zwraca Database instance
 */
function db() {
    return Database::getInstance();
}
