<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class CsrfMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === 'POST') {
            $body      = (array) $request->getParsedBody();
            $submitted = $body['csrf_token']
                ?? $request->getHeaderLine('X-CSRF-Token')
                ?? '';

            $token = $_SESSION['csrf_token'] ?? '';

            if ($token === '' || !hash_equals($token, $submitted)) {
                $response = new SlimResponse();
                $response->getBody()->write('Invalid CSRF token.');
                return $response->withStatus(403);
            }
        }

        return $handler->handle($request);
    }
}
