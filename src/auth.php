<?php
declare(strict_types=1);

/**
 * Get the currently authenticated user from the session.
 * Caches the DB lookup for the lifetime of the request.
 * Returns null if not authenticated.
 */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }

    if (empty($_SESSION['user_id'])) {
        $user = null;
        return null;
    }

    $stmt = get_db()->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

/**
 * Require an authenticated user. Redirects to sign-in if not logged in.
 */
function require_auth(): void
{
    if (current_user() === null) {
        flash('info', 'Please sign in to continue.');
        redirect('/?page=signin');
    }
}

/**
 * Log a user in by ID. Regenerates the session ID to prevent fixation.
 */
function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    // Clear pre-auth CSRF token so a fresh one is issued for the authenticated session
    unset($_SESSION['csrf_token']);
}

/**
 * Log out the current user.
 * Destroys the session and removes the remember-me cookie and DB token.
 */
function logout_user(): void
{
    if (!empty($_COOKIE['remember'])) {
        $parts = explode(':', $_COOKIE['remember'], 2);
        if (count($parts) === 2) {
            [$selector] = $parts;
            if (ctype_xdigit($selector) && strlen($selector) === 24) {
                $stmt = get_db()->prepare('DELETE FROM remember_tokens WHERE selector = ?');
                $stmt->execute([$selector]);
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

    // Instruct the browser to delete the session cookie
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
 * Attempt to authenticate with email and password.
 * Always runs password_verify to prevent user enumeration via timing.
 * Returns the user row on success, null on failure.
 */
function attempt_login(string $email, string $password): ?array
{
    // Pre-computed dummy hash ensures constant-time response
    // regardless of whether the email exists in the database.
    static $dummy_hash = null;
    if ($dummy_hash === null) {
        $dummy_hash = password_hash('__dummy_never_matches__', PASSWORD_ARGON2ID);
    }

    $stmt = get_db()->prepare('SELECT id, name, email, password FROM users WHERE email = ?');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch() ?: null;

    $hash  = $user ? $user['password'] : $dummy_hash;
    $valid = password_verify($password, $hash);

    return ($valid && $user) ? $user : null;
}

/**
 * Register a new user. Returns the new user's ID.
 * Throws PDOException on duplicate email (SQLSTATE 23000).
 */
function register_user(string $name, string $email, string $password): int
{
    $hash = password_hash($password, PASSWORD_ARGON2ID);
    $stmt = get_db()->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
    $stmt->execute([trim($name), strtolower(trim($email)), $hash]);
    return (int) get_db()->lastInsertId();
}

/**
 * Create a remember-me token using the split-token pattern:
 * - selector: used for DB lookup (stored in plaintext)
 * - rawToken: the secret (stored as SHA-256 hash; only raw value lives in cookie)
 * Sets the 'remember' cookie valid for 30 days.
 */
function set_remember_me_cookie(int $userId): void
{
    $selector  = bin2hex(random_bytes(12));  // 24 hex chars
    $rawToken  = bin2hex(random_bytes(32));  // 64 hex chars
    $tokenHash = hash('sha256', $rawToken);
    $expires   = date('Y-m-d H:i:s', strtotime('+30 days'));

    $stmt = get_db()->prepare(
        'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $selector, $tokenHash, $expires]);

    setcookie('remember', $selector . ':' . $rawToken, [
        'expires'  => time() + 60 * 60 * 24 * 30,
        'path'     => '/',
        'httponly' => true,
        'secure'   => false, // Set to true in production (HTTPS)
        'samesite' => 'Lax',
    ]);
}

/**
 * Resolve the remember-me cookie and log the user in automatically.
 * Rotates the token on each use to limit the window for stolen cookie abuse.
 * Called once per request in index.php before route dispatch.
 */
function resolve_remember_me(): void
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

    // Validate expected hex format to reject garbage early
    if (!ctype_xdigit($selector) || strlen($selector) !== 24) {
        return;
    }
    if (!ctype_xdigit($rawToken) || strlen($rawToken) !== 64) {
        return;
    }

    $pdo = get_db();

    // Probabilistic cleanup of expired tokens (~1% of requests)
    if (random_int(1, 100) === 1) {
        $pdo->query("DELETE FROM remember_tokens WHERE expires_at < datetime('now')");
    }

    $stmt = $pdo->prepare(
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
        // Token mismatch — possible theft. Wipe all tokens for this user.
        $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $stmt->execute([$row['user_id']]);
        setcookie('remember', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        return;
    }

    // Valid — rotate the token (delete old, issue new)
    $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE id = ?');
    $stmt->execute([$row['id']]);

    login_user((int) $row['user_id']);
    set_remember_me_cookie((int) $row['user_id']);
}
