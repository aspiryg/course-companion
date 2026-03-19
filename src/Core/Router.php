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

	    private function matchPattern(string $pattern, string $uri): array
    {
        // Escape forward slashes in the pattern for use in regex delimiter
        // Replace {paramName} with named regex capture groups
        $regex = preg_replace(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            '(?P<$1>[^/]+)',
            $pattern
        );
 
        // Add anchors: ^ = start of string, $ = end of string
        // This prevents /courses/5/extra from matching /courses/{id}
        $regex = '#^' . $regex . '$#';
 
        if (!preg_match($regex, $uri, $matches)) {
            return [false, []];
        }
 
        // Filter out numeric keys — preg_match includes both named and
        // positional captures; we only want the named ones.
        $params = array_filter(
            $matches,
            fn($key) => is_string($key),
            ARRAY_FILTER_USE_KEY
        );
 
        return [true, $params];
    }
}
