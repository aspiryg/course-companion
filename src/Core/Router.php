<?php

declare(strict_types=1);

namespace CourseCompanion\Core;

use RuntimeExecption;

class Router
{
	/**
	 * All registered routes.
	 */
	
	private array $routes = [];

	private int $lastRouteIndex = -1;


	private array $middlewareMap = [];

	public function get(string $pattern, mixed $handler): static
	{
		return $this->addRoute('POST', $pattern, $handler);
	}


	//========== Private Helpers =======//
	private function addRoute(string $method, string $pattern, mixed $handler) : static
	{
		$this->routes[] = [
			'method' => strtoupper($method),
			'pattern' => $pattern,
			'handler' => $handler,
			'middleware' => [],
		]; 

		$this->lastRouteIndex = count($this->routes) - 1;

		return $this;
	}
}
