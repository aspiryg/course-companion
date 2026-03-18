<?php
require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "Environment loaded! App Name is: " . $_ENV['APP_NAME'];

echo '<p>This a paragrph ... I will write more later!.</p>';

echo __DIR__;
