<?php 
/**
	The single source of truth
*/

return [
  'name' => $_ENV['APP_NAME'] ?? 'Course Companion',
  'env'	 => $_ENV['APP_ENV'] ?? 'production',
  'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
  'url' => $_ENV['APP_URL'] ?? 'http://localhost',  

  // Secret
  'secret' => $_ENV['APP_SECRET'] ?? 'my-secret',

  // Database
  'database' => [
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => $_ENV['DB_PORT'] ?? '3306',
    'name' => $_ENV['DB_NAME'] ?? 'course_companion',
    'user' => $_ENV['DB_USER'] ?? 'root',
    'pass' => $_ENV['DB_PASS'] ?? '',
    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
  //PDO options
  'options' => [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ],
  ],

  // Session configuration

  'session' => [
	  'name' => $_ENV['SESSION_NAME'] ?? 'cc_session',
	  'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 7200),
	  'secure' => filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
  ],

  'csv' => [
	  'max_rows' => (int)($_ENV['CSV_MAX_ROWS'] ?? 10000),
  ],

  // paths
  'paths' => [
	  'root' => dirname(__DIR__),
	  'src' => dirname(__DIR__) . '/src',
	  'templates' => dirname(__DIR__) . '/templates',
          'public' => dirname(__DIR__) . '/public',
          'database' => dirname(__DIR__) . '/database',
  ],
];

