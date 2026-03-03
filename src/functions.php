<?php
declare(strict_types=1);

/**
 * Escape a value for safe HTML output.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirect to a URL.
 * For HTMX requests, use the HX-Redirect header so HTMX performs a full navigation.
 */
function redirect(string $url): never
{
    // Enforce relative URLs only — prevents open redirect to external sites
    if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
        $url = '/';
    }

    if (is_htmx()) {
        header('HX-Redirect: ' . $url);
        http_response_code(200);
    } else {
        header('Location: ' . $url);
        http_response_code(303);
    }
    exit;
}

/**
 * Check if the current request was initiated by HTMX.
 */
function is_htmx(): bool
{
    return isset($_SERVER['HTTP_HX_REQUEST']);
}

/**
 * Store a flash message to display on the next request.
 */
function flash(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

/**
 * Read and clear a flash message. Returns null if not set.
 */
function get_flash(string $key): ?string
{
    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $message;
}

/**
 * Return a deterministic pastel cover color for a group based on its ID.
 */
function group_color(int $id): string
{
    $palette = ['#ddd6fe', '#bbf7d0', '#fed7aa', '#bae6fd', '#fde68a', '#fbcfe8'];
    return $palette[$id % count($palette)];
}

/**
 * Finish rendering a page.
 * Call after ob_start() and the page's HTML output.
 * - HTMX requests: echoes the captured fragment directly.
 * - Direct requests: wraps the fragment in the full layout.
 *
 * Usage in page files:
 *   ob_start();
 *   // ... HTML ...
 *   render('Page Title — Kai');
 */
function render(string $title): never
{
    $fragment = ob_get_clean();
    if (is_htmx()) {
        echo $fragment;
    } else {
        $content = $fragment;
        include __DIR__ . '/../templates/layout.php';
    }
    exit;
}
