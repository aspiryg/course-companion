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
  'secret' => $_ENV['APP_SECRET'] ?? 'my-secrect',

  // Database
  'database' => [
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => $_ENV['PORT'] ?? '3306',
    'name' => $_ENV['DB_NAME'] ?? 'course_companion',
  ]
];