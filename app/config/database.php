<?php
/**
 * Gaggle NFT — Database Connection
 * 
 * Singleton PDO connection factory.
 * Uses prepared statements exclusively.
 */

class Database {
    private static ?PDO $instance = null;

    /**
     * Get singleton PDO connection.
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = env('DB_HOST', 'localhost');
            $port = env('DB_PORT', '3306');
            $name = env('DB_NAME', 'gaggle_nft');
            $user = env('DB_USER', 'root');
            $pass = env('DB_PASSWORD', '');
            $charset = env('DB_CHARSET', 'utf8mb4');

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",
                ]);
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    throw $e;
                }
                error_log('Database connection failed: ' . $e->getMessage());
                die('Database connection error. Please try again later.');
            }
        }

        return self::$instance;
    }

    /**
     * Shorthand to get connection.
     */
    public static function db(): PDO {
        return self::getConnection();
    }

    // Prevent cloning/unserialization
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() { throw new \Exception('Cannot unserialize singleton'); }
}
