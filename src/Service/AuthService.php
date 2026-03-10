<?php
declare(strict_types=1);

namespace App\Service;

use PDO;

class AuthService
{
    // Per-request cache — AuthService is a singleton in the DI container
    private ?array $user       = null;
    private bool   $userLoaded = false;
    private int    $cachedId   = 0;

    public function __construct(private PDO $db) {}

    /**
     * Get a user by ID. Cached for the lifetime of the request.
     */
    public function getUserById(int $id): ?array
    {
        if (!$this->userLoaded || $this->cachedId !== $id) {
            $this->userLoaded = true;
            $this->cachedId   = $id;
            $stmt = $this->db->prepare(
                'SELECT id, name, email, city_id, created_at FROM users WHERE id = ?'
            );
            $stmt->execute([$id]);
            $this->user = $stmt->fetch() ?: null;
        }

        return $this->user;
    }

    /**
     * Attempt login. Always calls password_verify to prevent user enumeration via timing.
     * Returns the user row on success, null on failure.
     */
    public function attemptLogin(string $email, string $password): ?array
    {
        static $dummyHash = null;

        if ($dummyHash === null) {
            $dummyHash = password_hash('__dummy_never_matches__', PASSWORD_ARGON2ID);
        }

        $stmt = $this->db->prepare(
            'SELECT id, name, email, password FROM users WHERE email = ?'
        );
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch() ?: null;

        $hash  = $user ? $user['password'] : $dummyHash;
        $valid = password_verify($password, $hash);

        return ($valid && $user) ? $user : null;
    }

    /**
     * Register a new user. Returns the new user's ID.
     * Throws PDOException on duplicate email (SQLSTATE 23000).
     */
    public function registerUser(string $name, string $email, string $password, int $cityId): int
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password, city_id) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([trim($name), strtolower(trim($email)), $hash, $cityId]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Log a user in by ID. Regenerates the session ID to prevent fixation.
     */
    public function loginUser(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        // Invalidate the pre-login CSRF token so a fresh one is issued
        unset($_SESSION['csrf_token']);
        // Ensure a new CSRF token is available immediately
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /**
     * Log out the current user. Destroys the session and removes the remember-me cookie.
     */
    public function logoutUser(): void
    {
        if (!empty($_COOKIE['remember'])) {
            $parts = explode(':', $_COOKIE['remember'], 2);
            if (count($parts) === 2) {
                [$selector] = $parts;
                if (ctype_xdigit($selector) && strlen($selector) === 24) {
                    $this->db->prepare('DELETE FROM remember_tokens WHERE selector = ?')
                        ->execute([$selector]);
                }
            }
            setcookie('remember', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        $_SESSION = [];
        session_destroy();

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 3600,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?: 'Lax',
            ]);
        }
    }

    /**
     * Create a remember-me token using the split-token pattern.
     * Selector stored in plaintext; raw token stored as SHA-256 hash.
     */
    public function setRememberMeCookie(int $userId, bool $secure = false): void
    {
        $selector  = bin2hex(random_bytes(12));  // 24 hex chars
        $rawToken  = bin2hex(random_bytes(32));  // 64 hex chars
        $tokenHash = hash('sha256', $rawToken);
        $expires   = date('Y-m-d H:i:s', strtotime('+30 days'));

        $this->db->prepare(
            'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)'
        )->execute([$userId, $selector, $tokenHash, $expires]);

        setcookie('remember', $selector . ':' . $rawToken, [
            'expires'  => time() + 60 * 60 * 24 * 30,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $secure,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Resolve the remember-me cookie and auto-login the user.
     * Rotates the token on each use to limit stolen-cookie abuse window.
     * Called once per request in SessionMiddleware.
     */
    public function resolveRememberMe(bool $secure = false): void
    {
        if (!empty($_SESSION['user_id'])) {
            return;
        }

        if (empty($_COOKIE['remember'])) {
            return;
        }

        $parts = explode(':', $_COOKIE['remember'], 2);
        if (count($parts) !== 2) {
            return;
        }

        [$selector, $rawToken] = $parts;

        if (!ctype_xdigit($selector) || strlen($selector) !== 24) {
            return;
        }
        if (!ctype_xdigit($rawToken) || strlen($rawToken) !== 64) {
            return;
        }

        // Probabilistic cleanup of expired tokens (~1% of requests)
        if (random_int(1, 100) === 1) {
            $this->db->query("DELETE FROM remember_tokens WHERE expires_at < datetime('now')");
        }

        $stmt = $this->db->prepare(
            "SELECT id, user_id, token_hash
             FROM remember_tokens
             WHERE selector = ? AND expires_at > datetime('now')"
        );
        $stmt->execute([$selector]);
        $row = $stmt->fetch();

        if (!$row) {
            setcookie('remember', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
            return;
        }

        $expectedHash = hash('sha256', $rawToken);
        if (!hash_equals($row['token_hash'], $expectedHash)) {
            // Possible token theft — wipe all tokens for this user
            $this->db->prepare('DELETE FROM remember_tokens WHERE user_id = ?')
                ->execute([$row['user_id']]);
            setcookie('remember', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
            return;
        }

        // Valid — rotate: delete old token, issue new one
        $this->db->prepare('DELETE FROM remember_tokens WHERE id = ?')
            ->execute([$row['id']]);

        $this->loginUser((int) $row['user_id']);
        $this->setRememberMeCookie((int) $row['user_id'], $secure);
    }
}
