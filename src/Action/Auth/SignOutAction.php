<?php
declare(strict_types=1);

namespace App\Action\Auth;

use App\Service\AuthService;
use App\Support\RedirectTrait;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;

class SignOutAction
{
    use RedirectTrait;

    public function __construct(
        private AuthService $authService,
        private Messages    $flash,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $this->authService->logoutUser();
        $this->flash->addMessage('info', 'You have been signed out.');
        return $this->redirect($response, $request, '/');
    }
}
