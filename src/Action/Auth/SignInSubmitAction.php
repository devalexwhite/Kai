<?php
declare(strict_types=1);

namespace App\Action\Auth;

use App\Service\AuthService;
use App\Support\RedirectTrait;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class SignInSubmitAction
{
    use RedirectTrait;

    public function __construct(
        private Twig        $twig,
        private AuthService $authService,
        private Messages    $flash,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $body     = (array) $request->getParsedBody();
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        $remember = !empty($body['remember']);

        $user = $this->authService->attemptLogin($email, $password);

        if ($user) {
            $this->authService->loginUser((int) $user['id']);
            if ($remember) {
                $this->authService->setRememberMeCookie((int) $user['id']);
            }
            $this->flash->addMessage('success', 'Welcome back, ' . $user['name'] . '!');
            return $this->redirect($response, $request, '/dashboard');
        }

        return $this->twig->render($response, 'auth/signin.html.twig', [
            'error' => 'Email or password is incorrect.',
            'old'   => ['email' => $email],
        ]);
    }
}
