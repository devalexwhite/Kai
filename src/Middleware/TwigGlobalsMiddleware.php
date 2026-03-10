<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class TwigGlobalsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Twig     $twig,
        private Messages $flash,
    ) {}

    public function process(Request $request, RequestHandler $handler): Response
    {
        $env = $this->twig->getEnvironment();
        $env->addGlobal('current_user', $request->getAttribute('user'));
        $env->addGlobal('csrf_token', $_SESSION['csrf_token'] ?? '');
        $env->addGlobal('flash', $this->flash);

        return $handler->handle($request);
    }
}
