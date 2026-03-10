<?php
declare(strict_types=1);

use App\Middleware\CsrfMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\SessionMiddleware;
use App\Middleware\TwigGlobalsMiddleware;
use Slim\App;

return function (App $app): void {
    // Registration is LIFO: last added = outermost = runs first.
    // Execution order: SecurityHeaders → Session → Csrf → TwigGlobals → [Route] → Action
    $app->add(TwigGlobalsMiddleware::class);
    $app->add(CsrfMiddleware::class);
    $app->add(SessionMiddleware::class);
    $app->add(SecurityHeadersMiddleware::class);
};
