<?php
declare(strict_types=1);

namespace App\Action\Auth;

use App\Support\RedirectTrait;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class SignInAction
{
    use RedirectTrait;

    public function __construct(private Twig $twig) {}

    public function __invoke(Request $request, Response $response): Response
    {
        if ($request->getAttribute('user') !== null) {
            return $this->redirect($response, $request, '/dashboard');
        }

        return $this->twig->render($response, 'auth/signin.html.twig');
    }
}
