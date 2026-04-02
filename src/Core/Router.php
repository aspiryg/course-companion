<?php

declare(strict_types=1);

/**
 * =============================================================================
 * src/Core/Router.php — HTTP Router
 * =============================================================================
 *
 * WHAT THIS FILE DOES:
 * Maps incoming HTTP requests (method + URL path) to PHP functions/methods.
 *
 * WITHOUT A ROUTER, you'd need a separate PHP file for each page:
 *   /login.php, /dashboard.php, /courses.php...
 * This is the old way — messy, hard to maintain, insecure.
 *
 * WITH A ROUTER (the "Front Controller" pattern):
 *   All requests go to public/index.php (one file).
 *   The router reads the URL and calls the right code.
 *   Example: GET /courses → CourseController::index()
 *
 * ROUTE DEFINITION SYNTAX (what you'll write in index.php):
 *   $router->get('/courses', [CourseController::class, 'index']);
 *   $router->post('/courses', [CourseController::class, 'store']);
 *   $router->get('/courses/{id}', [CourseController::class, 'show']);
 *
 * URL PARAMETERS:
 *   {id} in a route pattern becomes $params['id'] in your controller.
 *   /courses/42 → $params = ['id' => '42']
 *
 * MIDDLEWARE SUPPORT:
 *   Routes can require middleware to run first (e.g., auth check).
 *   $router->get('/dashboard', [DashboardController::class, 'index'])
 *          ->middleware('auth');
 *
 * HOW IT WORKS INTERNALLY:
 *   1. Routes are stored as: [method, pattern, handler, middlewares]
 *   2. On dispatch(), we loop through routes
 *   3. Convert {id} style patterns to regex: /courses/(\d+|[^/]+)
 *   4. If pattern matches current URL → run middleware → call handler
 *   5. If nothing matches → 404 response
 * =============================================================================
 */

namespace CourseCompanion\Core;

use RuntimeException;

class Router
{
    /**
     * All registered routes.
     * Structure: [
     *   ['method' => 'GET', 'pattern' => '/courses/{id}', 'handler' => [...], 'middleware' => []]
     * ]
     *
     * @var array<int, array{method: string, pattern: string, handler: mixed, middleware: list<string>}>
     */
    private array $routes = [];

    /**
     * The last-added route (for chaining ->middleware() onto route definitions).
     * Points to the index in $this->routes.
     */
    private int $lastRouteIndex = -1;

    /**
     * Registered middleware classes, keyed by alias.
     * Example: ['auth' => AuthMiddleware::class]
     *
     * @var array<string, class-string>
     */
    private array $middlewareMap = [];

    // -------------------------------------------------------------------------
    // Route Registration Methods
    // -------------------------------------------------------------------------

    /**
     * Register a GET route.
     * GET is for reading data (loading a page, fetching a resource).
     * GET requests should be idempotent — calling them multiple times
     * has the same effect as calling once (no side effects like DB writes).
     */
    public function get(string $pattern, mixed $handler): static
    {
        return $this->addRoute('GET', $pattern, $handler);
    }

    /**
     * Register a POST route.
     * POST is for creating data or actions with side effects (form submits).
     */
    public function post(string $pattern, mixed $handler): static
    {
        return $this->addRoute('POST', $pattern, $handler);
    }

    /**
     * Register a PUT route.
     * PUT replaces an entire resource. Idempotent.
     * Example: PUT /courses/5  →  replace the whole course record
     */
    public function put(string $pattern, mixed $handler): static
    {
        return $this->addRoute('PUT', $pattern, $handler);
    }

    /**
     * Register a DELETE route.
     * DELETE removes a resource.
     */
    public function delete(string $pattern, mixed $handler): static
    {
        return $this->addRoute('DELETE', $pattern, $handler);
    }

    /**
     * Register a PATCH route.
     * PATCH partially updates a resource (only the fields you send).
     * Contrast with PUT which replaces the whole thing.
     */
    public function patch(string $pattern, mixed $handler): static
    {
        return $this->addRoute('PATCH', $pattern, $handler);
    }

    /**
     * Attach middleware to the most recently registered route.
     * Returns $this so you can chain: $router->get(...)->middleware('auth')
     *
     * @param string ...$aliases  One or more middleware names (from registerMiddleware)
     */
    public function middleware(string ...$aliases): static
    {
        if ($this->lastRouteIndex === -1) {
            throw new RuntimeException('No route to attach middleware to.');
        }

        foreach ($aliases as $alias) {
            $this->routes[$this->lastRouteIndex]['middleware'][] = $alias;
        }

        return $this;
    }

    /**
     * Register a middleware class under a short alias.
     *
     * @param string       $alias  Short name used in ->middleware('auth')
     * @param class-string $class  Fully qualified class name of the middleware
     */
    public function registerMiddleware(string $alias, string $class): void
    {
        $this->middlewareMap[$alias] = $class;
    }

    // -------------------------------------------------------------------------
    // Dispatch — match and execute a route
    // -------------------------------------------------------------------------

    /**
     * Match the current HTTP request to a route and execute it.
     *
     * This is called once per request, in public/index.php.
     *
     * @param string $method  HTTP method (GET, POST, etc.)
     * @param string $uri     The request path, e.g. /courses/5
     */
    public function dispatch(string $method, string $uri): void
    {
        // Strip query string from URI: /courses?page=2 → /courses
        $uri = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Normalize: ensure single leading slash, no trailing slash
        $uri = '/' . trim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }

        foreach ($this->routes as $route) {
            // Check HTTP method first (cheap string compare)
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            // Convert route pattern to regex and try to match URI
            [$matched, $params] = $this->matchPattern($route['pattern'], $uri);

            if (!$matched) {
                continue;
            }

            // --- Match found! ---
            // Run middleware pipeline (each middleware can abort or continue)
            foreach ($route['middleware'] as $alias) {
                if (!isset($this->middlewareMap[$alias])) {
                    throw new RuntimeException("Middleware '$alias' not registered.");
                }
                $middlewareClass = $this->middlewareMap[$alias];
                $middleware = new $middlewareClass();
                $middleware->handle($params); // throws or redirects if check fails
            }

            // Execute the controller action
            $this->callHandler($route['handler'], $params);
            return;
        }

        // No route matched — send 404
        $this->notFound();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Store a route in the routes array.
     * Returns $this for fluent chaining.
     */
    private function addRoute(string $method, string $pattern, mixed $handler): static
    {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => [],
        ];

        $this->lastRouteIndex = count($this->routes) - 1;

        return $this;
    }

    /**
     * Convert a route pattern like /courses/{id} to a regex,
     * then test it against the actual URI.
     *
     * Returns [true, $params] on match, or [false, []] on no match.
     *
     * REGEX EXPLANATION:
     *   {id}  →  (?P<id>[^/]+)
     *   (?P<name>...)  is a PHP "named capture group"
     *   [^/]+  means "one or more characters that are NOT a slash"
     *   This captures the value between slashes.
     *
     * @return array{0: bool, 1: array<string, string>}
     */
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
// File: src/Core/Router.php
// Replace the entire callHandler() method with this:

private function callHandler(mixed $handler, array $params): void
{
    // ── Branch 1: Closure / anonymous function ──────────────────────────────
    // A Closure is an anonymous function object: fn($p) => ...
    // We check this with instanceof, not is_callable(), to avoid the
    // ambiguity described below.

    if ($handler instanceof \Closure) {
        $handler($params);
        return;
    }

    // ── Branch 2: [ClassName::class, 'methodName'] array ────────────────────
    // is_callable(['ClassName', 'method']) returns TRUE even for non-static
    // instance methods — it only checks that the method *exists*, not that
    // it can be invoked statically. So we deliberately skip is_callable()
    // and handle arrays directly, always creating a new instance.

    if (is_array($handler) && count($handler) === 2) {
        [$class, $method] = $handler;

        if (!class_exists($class)) {
            throw new RuntimeException("Controller class '$class' not found.");
        }

        $controller = new $class();

        if (!method_exists($controller, $method)) {
            throw new RuntimeException(
                "Method '$method' not found on controller '$class'."
            );
        }

        $controller->$method($params);
        return;
    }

    throw new RuntimeException(
        'Invalid route handler. Use a Closure or [ClassName::class, \'method\'].'
    );
}

    /**
     * Send a 404 Not Found response.
     * In a fuller app this would render a nice 404 template.
     */
    private function notFound(): void
    {
        http_response_code(404);
        echo json_encode([
            'error'   => 'Not Found',
            'message' => 'The requested route does not exist.',
        ]);
    }
}