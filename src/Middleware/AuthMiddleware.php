<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Flash\Messages;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private Messages $flash) {}

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getAttribute('user') === null) {
            $this->flash->addMessage('info', 'Please sign in to continue.');

            $response = new SlimResponse();

            if ($request->getHeaderLine('HX-Request') === 'true') {
                return $response
                    ->withHeader('HX-Redirect', '/signin')
                    ->withStatus(200);
            }

            return $response
                ->withHeader('Location', '/signin')
                ->withStatus(303);
        }

        return $handler->handle($request);
    }
}
