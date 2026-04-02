<?php

declare(strict_types=1);

/**
 * =============================================================================
 * src/Core/Session.php — Secure Session Management
 * =============================================================================
 *
 * WHAT ARE PHP SESSIONS?
 * HTTP is stateless — each request is independent. Sessions solve this:
 * PHP assigns a unique ID per visitor (stored in a cookie), and keeps
 * a server-side file matching that ID to stored data ($_SESSION).
 *
 * So when a user logs in, you store their user ID in $_SESSION['user'].
 * On the next request, PHP reads the cookie, finds their session file,
 * and $_SESSION['user'] is available again — that's how "staying logged in" works.
 *
 * WHY A WRAPPER CLASS?
 * You could use $_SESSION directly everywhere. But:
 * - You'd repeat the same security settings in multiple files
 * - Forgetting httponly or samesite opens security holes
 * - CSRF token generation would be scattered across templates
 * This class centralises all session logic in one place.
 *
 * ALL METHODS ARE STATIC because the session is global state — there is
 * only one session per request, so instantiation would be meaningless.
 * =============================================================================
 */

namespace CourseCompanion\Core;

class Session
{
    /**
     * Tracks whether session_start() has been called this request.
     * PHP throws a notice if you call session_start() twice.
     */
    private static bool $started = false;

    // =========================================================================
    // Lifecycle
    // =========================================================================

    /**
     * Configure and start the session.
     * Call this once in public/index.php before any output is sent.
     *
     * @param array $config  The 'session' sub-array from config/app.php
     */
    public static function start(array $config): void
    {
        // Guard: don't start twice (e.g., if a middleware or test already started it)
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Set the cookie name before starting the session.
        // Using a custom name (cc_session instead of PHPSESSID) is a minor
        // security improvement — it doesn't advertise that you're using PHP.
        session_name($config['name'] ?? 'cc_session');

        // Configure the session cookie parameters.
        // These settings go into the Set-Cookie header PHP sends to the browser.
        session_set_cookie_params([
            // How long the cookie lives in the browser (seconds).
            'lifetime' => $config['lifetime'] ?? 7200,

            // The cookie is valid for the entire site, not just one path.
            'path'     => '/',

            // secure: true = cookie only sent over HTTPS.
            // Keep false in development (no HTTPS), true in production.
            'secure'   => $config['secure'] ?? false,

            // httponly: true = JavaScript CANNOT read this cookie.
            // This is critical: it prevents XSS attacks from stealing sessions.
            // An attacker who injects <script> can't read document.cookie
            // to steal the session ID — it's invisible to JS.
            'httponly' => true,

            // samesite: Lax = cookie only sent on same-site requests,
            // EXCEPT for top-level GET navigations (clicking a link from outside).
            // This blocks CSRF: when evil.com makes your browser POST to our site,
            // the session cookie is NOT included because it's cross-site + POST.
            'samesite' => 'Lax',
        ]);

        session_start();
        self::$started = true;
    }

    /**
     * Fully destroy the session — call this on logout.
     *
     * This does THREE things because just session_destroy() isn't enough:
     * 1. Clear the server-side data ($_SESSION array)
     * 2. Expire the browser cookie (so it's gone from the browser too)
     * 3. Destroy the server-side session file
     */
    public static function destroy(): void
    {
        // Clear the in-memory session data
        $_SESSION = [];

        // Expire the session cookie in the browser.
        // We send the same cookie name with an expiry in the past.
        // The browser deletes any cookie with a past expiry date.
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,        // 42000 seconds in the past = definitely expired
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        self::$started = false;
    }

    /**
     * Regenerate the session ID.
     * Call this immediately after a successful login.
     *
     * WHY?  Session Fixation Attack:
     * 1. Attacker visits your site, gets session ID "abc123"
     * 2. Attacker tricks victim into using that same session ID (various methods)
     * 3. Victim logs in — now session "abc123" is authenticated
     * 4. Attacker already has "abc123" and is now also "logged in"
     *
     * Regenerating the ID after login creates a NEW session ID ("xyz789").
     * The attacker's "abc123" is now worthless — it's a different session.
     * The `true` argument deletes the old session file from the server.
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    // =========================================================================
    // Read / Write session data
    // =========================================================================

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    // =========================================================================
    // Flash messages
    // =========================================================================

    /**
     * Store a one-time message that survives one redirect.
     *
     * PATTERN:
     *   1. Login fails → flash('error', 'Invalid password') → redirect to /login
     *   2. /login page loads → getFlash('error') returns 'Invalid password'
     *      → message is displayed once → automatically deleted
     *
     * Without flash messages you'd have to pass error messages as query
     * parameters (/login?error=Invalid+password) which exposes them in
     * browser history, server logs, and analytics tools.
     *
     * @param string $type    A category key: 'error', 'success', 'warning', etc.
     * @param string $message The message to show.
     */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    /**
     * Read and delete a flash message of a given type.
     * Returns null if no message of that type was set.
     */
    public static function getFlash(string $type): ?string
    {
        $message = $_SESSION['_flash'][$type] ?? null;
        unset($_SESSION['_flash'][$type]);
        return $message;
    }

    /**
     * Read and clear ALL flash messages at once.
     * Useful for templates that loop over all pending messages.
     *
     * @return array<string, string>  e.g. ['error' => 'Invalid password']
     */
    public static function getAllFlashes(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flashes;
    }

    // =========================================================================
    // Current user helpers
    // =========================================================================

    /**
     * Return the logged-in user's data, or null if not logged in.
     * This data is stored by AuthService after a successful login.
     *
     * @return array{id: int, name: string, email: string, role: string}|null
     */
    public static function currentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function isLoggedIn(): bool
    {
        // Check for 'id' specifically — an empty or partial user array
        // shouldn't be treated as authenticated.
        return isset($_SESSION['user']['id']);
    }

    public static function userRole(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }

    // =========================================================================
    // CSRF protection
    // =========================================================================

    /**
     * Get (or lazily generate) a CSRF token for this session.
     *
     * WHAT IS CSRF?
     * Cross-Site Request Forgery tricks the browser of a logged-in user
     * into sending a forged request to your app.
     *
     * Example without protection:
     *   1. You're logged into coursecompanion.dev
     *   2. You visit evil.com which has: <img src="http://coursecompanion.dev/delete-account">
     *   3. Your browser fetches that URL, including your session cookie
     *   4. Your account gets deleted — you never clicked anything
     *
     * HOW TOKENS STOP THIS:
     *   Every form includes a hidden field: <input name="_csrf" value="TOKEN">
     *   When submitted, we check that the posted TOKEN matches what's in
     *   the session. evil.com cannot read your session, so it cannot
     *   forge a valid TOKEN. No match → request rejected.
     *
     * random_bytes(32) = 32 bytes = 256 bits of cryptographic randomness.
     * bin2hex() converts binary to a 64-character hex string safe for HTML.
     */
    public static function csrfToken(): string
    {
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    /**
     * Validate a CSRF token submitted with a form.
     *
     * WHY hash_equals() and not ===?
     * Regular strcmp/=== has a timing side channel:
     * it returns false FASTER when the first character doesn't match
     * than when the first 60 characters match.
     * An attacker measuring millions of response times can use this to
     * guess the token one character at a time (timing attack).
     *
     * hash_equals() takes the same time regardless of where strings differ.
     * This is called "constant-time comparison" and is required for secrets.
     */
    public static function validateCsrf(string $token): bool
    {
        $stored = $_SESSION['_csrf_token'] ?? '';
        return hash_equals($stored, $token);
    }
}