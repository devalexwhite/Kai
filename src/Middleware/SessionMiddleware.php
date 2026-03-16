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
        $isHttps = ($request->getUri()->getScheme() === 'https');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '7200');
        ini_set('session.cookie_secure', $isHttps ? '1' : '0');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Ensure a CSRF token exists before any output or route handling
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        // Resolve remember-me cookie — may set $_SESSION['user_id'] via session_regenerate_id
        $this->authService->resolveRememberMe($isHttps);

        // Attach the authenticated user to the request for downstream middleware and actions
        $userId = $_SESSION['user_id'] ?? null;
        $user   = $userId !== null ? $this->authService->getUserById((int) $userId) : null;
        $request = $request->withAttribute('user', $user);

        $response = $handler->handle($request);

        // Write the session to disk now, before the response is emitted.
        // Without this, PHP flushes the session during shutdown — after Slim
        // has already sent the redirect to the browser — creating a race where
        // the next request (e.g. GET /dashboard after login) arrives before the
        // new session file exists and gets a blank session with a mismatched
        // CSRF token.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        return $response;
    }
}
