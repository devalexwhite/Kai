<?php
declare(strict_types=1);

namespace App\Action\Groups;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class GroupListAction
{
    public function __construct(
        private Twig $twig,
        private PDO  $db,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $groups = $this->db->query("
            SELECT g.id, g.slug, g.name, g.description,
                   c.name AS city_name, c.state AS city_state,
                   COUNT(m.id) AS member_count
            FROM user_groups g
            JOIN cities c ON c.id = g.city_id
            LEFT JOIN group_members m ON m.group_id = g.id
            GROUP BY g.id
            ORDER BY member_count DESC, g.created_at DESC
        ")->fetchAll();

        return $this->twig->render($response, 'groups/list.html.twig', [
            'groups' => $groups,
        ]);
    }
}
