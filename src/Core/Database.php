<?php

declare(strict_types=1);

/**
 * =============================================================================
 * src/Core/Database.php — Database Connection (Singleton + PDO)
 * =============================================================================
 *
 * WHAT THIS FILE DOES:
 * Provides a single, reusable PDO database connection for the entire app.
 *
 * DESIGN PATTERN — SINGLETON:
 * A Singleton ensures that only ONE instance of a class ever exists.
 * For a database connection, this is important: opening a new MySQL
 * connection is expensive (takes ~10-50ms). We open it once and reuse it.
 *
 * HOW TO USE:
 *   $db = Database::getInstance();
 *   $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
 *   $stmt->execute([$id]);
 *   $user = $stmt->fetch();
 *
 * WHY PDO (PHP Data Objects)?
 * PDO is a database-agnostic abstraction layer. The same PDO code works with
 * MySQL, PostgreSQL, SQLite, etc. — you only change the DSN string.
 * More importantly, PDO's prepared statements are the PRIMARY defense
 * against SQL Injection attacks.
 *
 * SQL INJECTION EXAMPLE (the threat):
 *   // DANGEROUS — never do this:
 *   $query = "SELECT * FROM users WHERE email = '$email'";
 *   // If $email = "' OR 1=1 --", the query becomes:
 *   // SELECT * FROM users WHERE email = '' OR 1=1 --'
 *   // That returns ALL users. An attacker has bypassed auth.
 *
 *   // SAFE — always do this:
 *   $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
 *   $stmt->execute([$email]);
 *   // PDO sends the query and the data SEPARATELY to MySQL.
 *   // The data can never become part of the SQL logic. Safe!
 *
 * NAMESPACE:
 * CourseCompanion\Core groups the foundational infrastructure classes.
 * PHP namespaces prevent name collisions — your "Database" class won't
 * conflict with another library's "Database" class.
 * =============================================================================
 */

namespace CourseCompanion\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    /**
     * The single PDO instance shared across the whole application.
     * The ? means it can be null (before first use) or a PDO object.
     * This is PHP 8's "nullable type" syntax.
     */
    private static ?PDO $instance = null;

    /**
     * Stores the config array so getInstance() can read it.
     * Set once via Database::configure() before any DB call.
     */
    private static array $config = [];

    /**
     * Private constructor — the core of the Singleton pattern.
     * Making this private means you CANNOT do: new Database()
     * The only way to get the connection is through getInstance().
     * This enforces that there is always exactly one connection.
     */
    private function __construct() {}

    /**
     * Also prevent cloning — another way Singleton could be broken.
     */
    private function __clone() {}

    /**
     * Configure the database with settings from config/app.php.
     * Call this once at bootstrap (public/index.php) before any DB use.
     *
     * @param array $config  The 'database' sub-array from config/app.php
     */
    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Get (or create) the single PDO instance.
     *
     * The DSN (Data Source Name) is the connection string that tells PDO:
     *   - Which driver to use (mysql:)
     *   - Where the server is (host, port)
     *   - Which database to select (dbname)
     *   - What character set to use (charset)
     *
     * @throws RuntimeException if configuration hasn't been provided
     * @throws PDOException     if the connection fails (wrong credentials etc.)
     * @return PDO
     */
    public static function getInstance(): PDO
    {
        // If we already have a connection, return it immediately.
        // This is the "only one instance" part of Singleton.
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (empty(self::$config)) {
            throw new RuntimeException(
                'Database not configured. Call Database::configure($config) first.'
            );
        }

        $cfg = self::$config;

        // Build the DSN string
        // charset=utf8mb4 means the DB can store ANY Unicode character,
        // including emoji (regular utf8 in MySQL only goes to 3-byte chars).
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['name'],
            $cfg['charset']
        );

        try {
            // Create the PDO connection.
            // Options are set in config/app.php and explain there.
            self::$instance = new PDO(
                $dsn,
                $cfg['user'],
                $cfg['pass'],
                $cfg['options'] ?? []
            );

        } catch (PDOException $e) {
            // We catch PDOException here to potentially log it securely,
            // then re-throw a more generic exception so we don't leak
            // database credentials in the error message to end users.
            // In production, you'd log $e->getMessage() to a log file.
            throw new RuntimeException(
                'Database connection failed. Check your .env configuration.',
                (int) $e->getCode(),
                $e   // Pass original as "previous exception" for debugging
            );
        }

        return self::$instance;
    }

    /**
     * Reset the connection — useful in tests to start fresh.
     * The null coalescing assignment (??=) is a PHP 8 feature.
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    // getConfig() could be added if you want to read the config elsewhere,
    public static function getConfig(): array
    {
        return self::$config;
    }
}