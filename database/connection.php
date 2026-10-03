<?php
/**
 * Database Connection (PDO) with MySQLi-compatible wrapper
 */

require_once dirname(__DIR__) . '/includes/config.php';

class Database {
    private $pdo;

    public function __construct() {
        try {
            if (
                !defined('DB_HOST') ||
                !defined('DB_USER') ||
                !defined('DB_PASSWORD') ||
                !defined('DB_NAME')
            ) {
                throw new Exception('Database configuration constants not defined');
            }

            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

            $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // store raw PDO instance
            $this->pdo = $pdo;

        } catch (Exception $e) {
            die('Database Error: ' . $e->getMessage());
        }
    }

    // `getConnection()` removed — use `getPDO()` instead

    public function getPDO()
    {
        return $this->pdo;
    }

    public function query($sql) {
        return $this->pdo->query($sql);
    }

    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }

}
// Global instance
$db = new Database();