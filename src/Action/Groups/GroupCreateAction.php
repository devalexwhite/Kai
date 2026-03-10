<?php
declare(strict_types=1);

namespace App\Action\Groups;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class GroupCreateAction
{
    public function __construct(
        private Twig $twig,
        private PDO  $db,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');

        $preselectedId = (int) $user['city_id'];
        $stmt = $this->db->prepare('SELECT id, name, state FROM cities WHERE id = ?');
        $stmt->execute([$preselectedId]);
        $preselected = $stmt->fetch() ?: null;

        $cities = $this->db->query('SELECT id, name, state FROM cities ORDER BY state, name')->fetchAll();

        return $this->twig->render($response, 'groups/create.html.twig', [
            'preselected' => $preselected,
            'cities'      => $cities,
        ]);
    }
}
