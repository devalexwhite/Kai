<?php
declare(strict_types=1);

namespace App\Action\Auth;

use App\Service\AuthService;
use App\Support\RedirectTrait;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class SignUpSubmitAction
{
    use RedirectTrait;

    public function __construct(
        private Twig        $twig,
        private PDO         $db,
        private AuthService $authService,
        private Messages    $flash,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $body     = (array) $request->getParsedBody();
        $name     = trim($body['name'] ?? '');
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        $cityId   = (int) ($body['city_id'] ?? 0);
        $remember = !empty($body['remember']);
        $next     = $this->validateNext($body['next'] ?? '');

        $errors = [];

        if (mb_strlen($name) < 2) {
            $errors['name'] = 'Name must be at least 2 characters.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Name must be under 100 characters.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if (mb_strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }

        if ($cityId === 0) {
            $errors['city_id'] = 'Please select a city.';
        } else {
            $cityCheck = $this->db->prepare('SELECT id FROM cities WHERE id = ?');
            $cityCheck->execute([$cityId]);
            if (!$cityCheck->fetch()) {
                $errors['city_id'] = 'Please select a valid city.';
            }
        }

        if (empty($errors)) {
            try {
                $userId = $this->authService->registerUser($name, $email, $password, $cityId);
                $this->authService->loginUser($userId);
                if ($remember) {
                    $this->authService->setRememberMeCookie($userId);
                }
                $this->flash->addMessage('success', 'Welcome to Kai, ' . $name . '!');
                return $this->redirect($response, $request, $next !== '' ? $next : '/dashboard');
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'UNIQUE constraint')) {
                    $errors['email'] = 'An account with this email already exists.';
                } else {
                    throw $e;
                }
            }
        }

        // Re-fetch preselected city for the widget on validation failure
        $preselected = null;
        if ($cityId > 0) {
            $stmt = $this->db->prepare('SELECT id, name, state FROM cities WHERE id = ?');
            $stmt->execute([$cityId]);
            $preselected = $stmt->fetch() ?: null;
        }

        $cities = $this->db->query('SELECT id, name, state FROM cities ORDER BY state, name')->fetchAll();

        return $this->twig->render($response, 'auth/signup.html.twig', [
            'errors'      => $errors,
            'old'         => ['name' => $name, 'email' => $email, 'city_id' => (string) $cityId],
            'preselected' => $preselected,
            'cities'      => $cities,
            'next'        => $next,
        ]);
    }

    private function validateNext(string $raw): string
    {
        $parsed = parse_url($raw);
        if (
            $raw !== ''
            && str_starts_with($raw, '/')
            && !str_starts_with($raw, '//')
            && empty($parsed['host'])
            && empty($parsed['scheme'])
        ) {
            return $raw;
        }
        return '';
    }
}
