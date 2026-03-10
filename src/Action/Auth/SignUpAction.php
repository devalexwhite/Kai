<?php
declare(strict_types=1);

namespace App\Action\Auth;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class SignUpAction
{
    use RedirectTrait;

    public function __construct(
        private Twig $twig,
        private PDO  $db,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        if ($request->getAttribute('user') !== null) {
            return $this->redirect($response, $request, '/dashboard');
        }

        $cities = $this->db->query('SELECT id, name, state FROM cities ORDER BY state, name')->fetchAll();

        return $this->twig->render($response, 'auth/signup.html.twig', [
            'cities' => $cities,
            'next'   => $this->validateNext($request->getQueryParams()['next'] ?? ''),
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
