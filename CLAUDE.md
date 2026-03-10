# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Tech Stack

- **PHP 8.2+** with **Slim Framework v4** — PSR-7/PSR-15, DI container via PHP-DI v7
- **Twig** — server-side templating via `slim/twig-view`
- **HTMX 1.9.12** — frontend interactivity via hypermedia exchanges (self-hosted at `public/htmx.min.js`)
- **Raw CSS** — no preprocessors, no build process (`public/css/app.css`)
- **SQLite** — database via PHP's PDO extension (`database/kai.sqlite`, gitignored)
- **Phinx** — database migrations (`robmorgan/phinx`)

## Development Commands

Install Composer dependencies:
```bash
composer install
```

Run database migrations:
```bash
vendor/bin/phinx migrate
```

Seed cities data:
```bash
vendor/bin/phinx seed:run
```

Start the PHP built-in dev server (web root is `public/`):
```bash
php -S localhost:8000 -t public
```

## File Structure

```
public/
  index.php         # Slim entry point — bootstraps app, middleware, routes
  css/app.css       # Single stylesheet
  htmx.min.js       # HTMX vendored locally — do not replace with CDN
config/
  container.php     # PHP-DI service definitions (PDO, Twig, AuthService, Messages)
  middleware.php    # Middleware registration (LIFO — last registered runs first)
  routes.php        # All route definitions
  settings.php      # App configuration array
src/
  Action/           # Single-action invokable handler classes (one per route)
    Auth/           # SignIn, SignUp, SignOut actions
    City/           # CitySearch action (HTMX city widget)
    Dashboard/      # Dashboard action
    Events/         # Event CRUD + RSVP + Browse actions
    Groups/         # Group CRUD + join/leave + past events actions
    Home/           # Home action
  Middleware/       # PSR-15 middleware classes
    AuthMiddleware.php          # Redirects unauthenticated requests
    CsrfMiddleware.php          # Verifies CSRF token on POST requests
    SecurityHeadersMiddleware.php
    SessionMiddleware.php       # Starts session, resolves remember-me
    TwigGlobalsMiddleware.php   # Injects current_user, csrf_token, flash into Twig
  Service/
    AuthService.php             # All auth logic as injectable class methods
  Support/
    RedirectTrait.php           # HTMX-aware redirect helper
  Twig/
    KaiExtension.php            # group_background(), event_countdown(), nl2br filter
templates/
  layout.html.twig  # Full HTML shell
  home.html.twig
  404.html.twig
  auth/             # signin.html.twig, signup.html.twig
  dashboard/        # dashboard.html.twig
  events/           # browse.html.twig, create.html.twig, edit.html.twig, view.html.twig
  groups/           # create.html.twig, edit.html.twig, list.html.twig, view.html.twig
  partials/         # nav, city_widget, city_chosen, city_search_results,
                    # explore_groups_grid, past_events, group_join_result
database/
  migrations/       # Phinx migration files
  seeds/            # Phinx seeder files (CitiesSeeder)
phinx.php           # Phinx configuration
```

## Architecture: Hypermedia Driven Application (HDA)

This project follows the HDA pattern. Key principles:

- **The server returns HTML, not JSON.** HTMX requests get HTML fragments back, not data for client-side rendering.
- **State lives on the server.** The URL and server-side session/database are the source of truth, not client-side JS state.
- **Interactions are hypermedia exchanges.** HTMX attributes (`hx-get`, `hx-post`, `hx-target`, `hx-swap`) drive partial page updates without writing JavaScript.
- **Forms and links are the primary interaction primitives.** Progressive enhancement — pages should be functional without HTMX where possible.

### Routing

All routes are defined in `config/routes.php`. Routes use clean path-based URLs (no query string dispatch). Protected routes/groups use `AuthMiddleware` as route middleware.

To add a new route: create an action class in `src/Action/`, add the route in `config/routes.php`, and register any DI dependencies in `config/container.php`.

### Action Class Pattern

Every action is a single-action invokable class:

```php
<?php
namespace App\Action\Groups;

use App\Support\RedirectTrait;
use App\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class GroupListAction
{
    use RedirectTrait;

    public function __construct(
        private readonly Twig $twig,
        private readonly \PDO $pdo,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        // ... query DB, render template ...
        return $this->twig->render($response, 'groups/list.html.twig', [
            'groups' => $groups,
        ]);
    }
}
```

### HTMX + CSRF

The CSRF token is sent with every HTMX request via `hx-headers` on `<body>` (set in `templates/layout.html.twig`). For standard form POSTs, include `<input type="hidden" name="csrf_token" value="{{ csrf_token }}">`. `CsrfMiddleware` checks both the `csrf_token` POST field and the `X-CSRF-Token` header.

Never use a CDN for HTMX — it is vendored at `public/htmx.min.js` to eliminate supply-chain risk.

### HTMX-Aware Redirects

Use `RedirectTrait::redirect()` in all action classes — it detects `HX-Request` and returns `HX-Redirect` (200) for HTMX requests or a standard 303 Location redirect for full-page navigations.

## Database

Schema is managed by Phinx migrations in `database/migrations/`. Tables:

- `cities` — `id`, `name`, `state`
- `users` — `id`, `name`, `email` (unique, case-insensitive), `city_id` (FK), `password` (Argon2ID hash), `created_at`
- `remember_tokens` — `id`, `user_id` (FK → users), `selector` (indexed), `token_hash` (SHA-256), `expires_at`, `created_at`
- `user_groups` — `id`, `name`, `description`, `city_id`, `creator_id`, `created_at`
- `group_members` — `id`, `group_id`, `user_id`, `joined_at` (unique: group_id+user_id)
- `group_events` — `id`, `group_id`, `creator_id`, `title`, `description`, `event_date`, `event_time`, `location`, `meeting_url`, `created_at`
- `event_rsvps` — `id`, `event_id`, `user_id`, `created_at` (unique: event_id+user_id)

Always use PDO prepared statements. Never interpolate user input into SQL.

## Authentication

- **Passwords** — `password_hash($p, PASSWORD_ARGON2ID)` / `password_verify()`
- **Login** — `AuthService::attemptLogin()` uses a dummy Argon2ID hash to ensure constant response time when the email doesn't exist (prevents user enumeration via timing)
- **Session** — `AuthService::loginUser()` calls `session_regenerate_id(true)` and clears the CSRF token
- **Logout** — `AuthService::logoutUser()` destroys the session, expires the session cookie, and removes the remember-me DB token
- **Remember me** — split-token pattern: `selector` (stored plaintext for DB lookup) + `rawToken` (stored as SHA-256 hash). Cookie holds `selector:rawToken` in hex. Token is rotated on every use.
- **Auth guard** — add `AuthMiddleware` to any route or route group that requires authentication

## Design and UX

- Lightweight, minimal styling. Should render on old hardware and slow connections.
- Always prefer native HTML elements.
- The site should operate without Javascript enabled with progressive fallback.
- Focus on accessibility and content. Use `:focus-visible` (not `:focus`) for keyboard focus styles.

## Security Checklist

When adding new features, verify:

- [ ] Twig auto-escaping handles output (never use `|raw` on user data)
- [ ] All POST handlers are covered by `CsrfMiddleware` (enabled globally for all POST requests)
- [ ] All DB queries use prepared statements (`$pdo->prepare()` + `->execute([...])`)
- [ ] New protected routes/groups add `AuthMiddleware` as route middleware
- [ ] Redirects use `RedirectTrait::redirect()` with hardcoded paths only
