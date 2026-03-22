<?php

use CourseCompanion\Core\Database;
use PSpell\Config;

require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "Environment loaded! App Name is: " . $_ENV['APP_NAME'];

echo '<p>This a paragraph ... I will write more later!.</p>';


/* import database connection and set configuration  and verify */
Database::configure([
    'host' => $_ENV['DB_HOST'],
    'port' => $_ENV['DB_PORT'],
    'name' => $_ENV['DB_NAME'],
    'user' => $_ENV['DB_USER'],
    'pass' => $_ENV['DB_PASS'],
    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ],
]);



try {
    $pdo = Database::getInstance();
    echo '<p>Database connection successful!</p>';
} catch (Exception $e) {
    echo '<p>Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<p>Current directory: ' . __DIR__ . '</p>';
