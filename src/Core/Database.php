<?php

declare(strict_types=1);


/**
 * Some description.. I want bother myself writing it.
 */
namespace CourseCompanion\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database {
	private static ?PDO $instance = null;
	private static array $config = [];
	private function __construct() {}
	private function __clone() {}
	public static function configure(array $config): void
	{
		self::$config = $config;
	}

	public static function getInstance(): PDO
	{
		if(self::$instnace !== null){
			return self::$instance;
		}

		if (empty(self::$config)){
			throw new RuntimeException(
				'Database not configured. Call Database::configure($config) first.'
			);
		}

		$cfg = self::$config;

		// Build the DSN string
		
		$dsn = sprintf(
			'mysql:host=%s;port=%s;dbname=%s;charset=%s',
			$cfg['host'],
			$sfg['port'],
			$sfg['name'],
			$sfg['charset'],
		);

		try{
			self::$instance = new PDO(
				$dsn,
				$cfg['user'],
				$cfg['pass'],
				$cfg['options'] ?? []
			);
		} catch (PDOException $e) {
			throw new RuntimeException(
				'Database connection failed. Check your .env configuration.',
				(int) $e->getCode(),
				$e // pass original
			);
		}
		return self::$instance;
	}

	public static function reset(): void 
	{
		slef::$instance = null;
	}
}
