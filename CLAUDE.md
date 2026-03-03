# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Tech Stack

- **PHP** — server-side rendering, no framework
- **HTMX 1.9.12** — frontend interactivity via hypermedia exchanges (self-hosted at `public/htmx.min.js`)
- **Raw CSS** — no preprocessors, no build process (`public/css/app.css`)
- **SQLite** — database via PHP's PDO extension (`database/kai.sqlite`, gitignored)

## Development Commands

Start the PHP built-in dev server:
```bash
php -S localhost:8000
```

Install/update Composer dependencies:
```bash
composer install
composer update
```

## File Structure

```
index.php           # Front controller — bootstraps everything, routes requests
src/
  session.php       # Hardened session_start() — loaded first in index.php
  db.php            # get_db(): PDO singleton, bootstraps SQLite schema on first call
  functions.php     # e(), redirect(), is_htmx(), flash(), get_flash(), render()
  csrf.php          # csrf_token(), csrf_field(), csrf_verify()
  auth.php          # current_user(), require_auth(), login_user(), logout_user(),
                    # attempt_login(), register_user(), set_remember_me_cookie(),
                    # resolve_remember_me()
pages/
  home.php          # Public homepage
  signup.php        # Registration form + POST handler
  signin.php        # Sign-in form + POST handler
  signout.php       # POST-only sign-out action
  dashboard.php     # Auth-gated dashboard (calls require_auth() at top)
templates/
  layout.php        # Full HTML shell — receives $title and $content
  nav.php           # Navigation partial (included by layout.php)
public/
  css/app.css       # Single stylesheet
  htmx.min.js       # HTMX vendored locally — do not replace with CDN
database/
  .gitkeep          # Directory is tracked; kai.sqlite is gitignored
```

## Architecture: Hypermedia Driven Application (HDA)

This project follows the HDA pattern. Key principles:

- **The server returns HTML, not JSON.** HTMX requests get HTML fragments back, not data for client-side rendering.
- **State lives on the server.** The URL and server-side session/database are the source of truth, not client-side JS state.
- **Interactions are hypermedia exchanges.** HTMX attributes (`hx-get`, `hx-post`, `hx-target`, `hx-swap`) drive partial page updates without writing JavaScript.
- **Forms and links are the primary interaction primitives.** Progressive enhancement — pages should be functional without HTMX where possible.

### Routing

`index.php` is the sole entry point. Routes are dispatched via `?page=<name>` query parameter against an explicit allowlist:

```php
$routes = ['home', 'signup', 'signin', 'signout', 'dashboard'];
$page   = $_GET['page'] ?? 'home';
// dispatches to pages/{$page}.php
```

To add a new route: create `pages/mypage.php` and add `'mypage'` to `$routes` in `index.php`.

### Page File Pattern

Every page file follows this structure:

```php
<?php
declare(strict_types=1);

// 1. Auth guard (if required)
require_auth();

// 2. POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    // ... validate, write to DB, redirect ...
}

// 3. Render
ob_start();
?>
<!-- HTML fragment here -->
<?php
render('Page Title — Kai'); // handles full layout vs HTMX fragment automatically
```

The `render(string $title): never` function in `src/functions.php` calls `ob_get_clean()`, then either echoes the fragment (HTMX request) or wraps it in `templates/layout.php` (direct navigation). Always use `render()` — never write the ob/dispatch block manually.

### HTMX + CSRF

The CSRF token is sent with every HTMX request via `hx-headers` on `<body>` (set in `templates/layout.php`). For standard form POSTs, `csrf_field()` renders a hidden input. `csrf_verify()` checks both locations.

Never use a CDN for HTMX — it is vendored at `public/htmx.min.js` to eliminate supply-chain risk.

## Database

SQLite schema is bootstrapped automatically in `get_db()` on first connection — no migration step needed in development. Tables:

- `users` — `id`, `name`, `email` (unique, case-insensitive), `password` (Argon2ID hash), `created_at`
- `remember_tokens` — `id`, `user_id` (FK → users), `selector` (plaintext, indexed), `token_hash` (SHA-256), `expires_at`

Always use PDO prepared statements. Never interpolate user input into SQL.

## Authentication

- **Passwords** — `password_hash($p, PASSWORD_ARGON2ID)` / `password_verify()`
- **Login** — `attempt_login(email, password)` uses a dummy Argon2ID hash to ensure constant response time when the email doesn't exist (prevents user enumeration via timing)
- **Session** — `login_user(int $userId)` calls `session_regenerate_id(true)` and clears the CSRF token so a fresh one is issued for the authenticated session
- **Logout** — `logout_user()` destroys the session, expires the session cookie explicitly, and removes the remember-me DB token
- **Remember me** — split-token pattern: `selector` (stored plaintext for DB lookup) + `rawToken` (stored as SHA-256 hash). Cookie holds `selector:rawToken` in hex. Token is rotated on every use.
- **Auth guard** — call `require_auth()` at the top of any protected page file

## Design and UX

- Lightweight, minimal styling. Should render on old hardware and slow connections.
- Always prefer native HTML elements.
- The site should operate without Javascript enabled with progressive fallback.
- Focus on accessibility and content. Use `:focus-visible` (not `:focus`) for keyboard focus styles.

## Security Checklist

When adding new features, verify:

- [ ] All user-supplied values output to HTML go through `e()`
- [ ] All POST handlers call `csrf_verify()` before any processing
- [ ] All DB queries use prepared statements (`$pdo->prepare()` + `->execute([...])`)
- [ ] New protected pages call `require_auth()` at the top
- [ ] `redirect()` only receives hardcoded relative paths (it enforces this but don't bypass it)
