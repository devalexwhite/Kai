<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(private AuthService $authService) {}

    public function process(Request $request, RequestHandler $handler): Response
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '7200');
        // Set to '1' when serving over HTTPS in production
        ini_set('session.cookie_secure', '0');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Ensure a CSRF token exists before any output or route handling
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        // Resolve remember-me cookie — may set $_SESSION['user_id'] via session_regenerate_id
        $this->authService->resolveRememberMe();

        // Attach the authenticated user to the request for downstream middleware and actions
        $userId = $_SESSION['user_id'] ?? null;
        $user   = $userId !== null ? $this->authService->getUserById((int) $userId) : null;
        $request = $request->withAttribute('user', $user);

        return $handler->handle($request);
    }
}
