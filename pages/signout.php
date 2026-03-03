<?php
declare(strict_types=1);

// Sign-out is POST-only to prevent logout via GET request (e.g., a link or img tag)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

csrf_verify();
logout_user();

flash('info', 'You have been signed out.');
redirect('/?page=home');
