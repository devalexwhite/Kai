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
        $activeTag = trim($request->getQueryParams()['tag'] ?? '');

        $sql = "
            SELECT g.id, g.slug, g.name, g.description,
                   c.name AS city_name, c.state AS city_state,
                   COUNT(DISTINCT m.id) AS member_count,
                   GROUP_CONCAT(t.name ORDER BY gt.created_at ASC SEPARATOR ',') AS tag_names
            FROM user_groups g
            JOIN cities c ON c.id = g.city_id
            LEFT JOIN group_members m ON m.group_id = g.id
            LEFT JOIN group_tags gt ON gt.group_id = g.id
            LEFT JOIN tags t ON t.id = gt.tag_id
        ";

        if ($activeTag !== '') {
            $sql .= " JOIN group_tags gta ON gta.group_id = g.id
                      JOIN tags ta ON ta.id = gta.tag_id AND ta.name = :tag ";
        }

        $sql .= " GROUP BY g.id ORDER BY member_count DESC, g.created_at DESC";

        $stmt = $this->db->prepare($sql);
        if ($activeTag !== '') {
            $stmt->bindValue(':tag', $activeTag);
        }
        $stmt->execute();
        $groups = $stmt->fetchAll();

        return $this->twig->render($response, 'groups/list.html.twig', [
            'groups'    => $groups,
            'activeTag' => $activeTag !== '' ? $activeTag : null,
        ]);
    }
}
