<?php
declare(strict_types=1);

namespace App\Action\Home;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class HomeAction
{
    public function __construct(
        private Twig $twig,
        private PDO  $db,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $user   = $request->getAttribute('user');
        $cityId = null;

        if ($user) {
            $cityId = (int) $user['city_id'];
        } else {
            $submitted = (int) ($request->getQueryParams()['city_id'] ?? 0);
            if ($submitted > 0) {
                $check = $this->db->prepare('SELECT id FROM cities WHERE id = ?');
                $check->execute([$submitted]);
                if ($check->fetch()) {
                    $cityId = $submitted;
                }
            }
        }

        $exploreCity = null;
        if ($cityId !== null) {
            $stmt = $this->db->prepare('SELECT id, name, state FROM cities WHERE id = ?');
            $stmt->execute([$cityId]);
            $exploreCity = $stmt->fetch() ?: null;
        }

        if ($exploreCity === null) {
            $exploreCity = $this->db->query("
                SELECT c.id, c.name, c.state
                FROM cities c
                JOIN user_groups g ON g.city_id = c.id
                GROUP BY c.id
                ORDER BY COUNT(g.id) DESC
                LIMIT 1
            ")->fetch() ?: null;
        }

        $groups = [];
        if ($exploreCity !== null) {
            $userId = $user ? (int) $user['id'] : 0;
            $stmt   = $this->db->prepare("
                SELECT g.id, g.name,
                       COUNT(DISTINCT m.id) AS member_count,
                       MIN(e.event_date) AS next_event_date,
                       EXISTS (SELECT 1 FROM group_members WHERE group_id = g.id AND user_id = ?) AS is_member
                FROM user_groups g
                LEFT JOIN group_members m ON m.group_id = g.id
                LEFT JOIN group_events e ON e.group_id = g.id AND e.event_date >= CURDATE()
                WHERE g.city_id = ?
                GROUP BY g.id
                ORDER BY
                    CASE WHEN MIN(e.event_date) IS NULL THEN 1 ELSE 0 END,
                    MIN(e.event_date) ASC,
                    COUNT(DISTINCT m.id) DESC
                LIMIT 6
            ");
            $stmt->execute([$userId, (int) $exploreCity['id']]);
            $groups = $stmt->fetchAll();
        }

        return $this->twig->render($response, 'home.html.twig', [
            'exploreCity' => $exploreCity,
            'groups'      => $groups,
        ]);
    }
}
