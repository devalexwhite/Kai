<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', '7200');
// Set to '1' when serving over HTTPS in production
ini_set('session.cookie_secure', '0');

session_start();
