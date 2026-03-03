<?php
declare(strict_types=1);

require_once __DIR__ . '/src/session.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/src/csrf.php';
require_once __DIR__ . '/src/auth.php';

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// Ensure CSRF token exists in session before any output
csrf_token();

// Resolve remember-me cookie before dispatching routes
resolve_remember_me();

// Route dispatch
$routes = ['home', 'signup', 'signin', 'signout', 'dashboard'];
$page   = $_GET['page'] ?? 'home';

if (!in_array($page, $routes, true)) {
    http_response_code(404);
    $title   = '404 Not Found';
    $content = '<section class="container"><h1>Page not found</h1><p><a href="/">Return home</a></p></section>';
    include __DIR__ . '/templates/layout.php';
    exit;
}

require_once __DIR__ . '/pages/' . $page . '.php';
