<?php
declare(strict_types=1);

/**
 * Get (or generate) the CSRF token for the current session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF input field for use inside <form> tags.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify the CSRF token from the current POST request.
 * Checks both the POST body and the X-CSRF-Token header (for non-form HTMX requests).
 * Exits with 403 on failure.
 */
function csrf_verify(): void
{
    $submitted = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';

    if (!hash_equals(csrf_token(), $submitted)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}
