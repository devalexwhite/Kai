<?php
declare(strict_types=1);

namespace App\Action\Groups;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class GroupMembersAction
{
    public function __construct(
        private Twig $twig,
        private PDO  $db,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];

        $stmt = $this->db->prepare("
            SELECT g.id, g.slug, g.name, g.creator_id,
                   c.name AS city_name, c.state AS city_state,
                   u.name AS creator_name
            FROM user_groups g
            JOIN cities c ON c.id = g.city_id
            JOIN users u  ON u.id = g.creator_id
            WHERE g.slug = ?
        ");
        $stmt->execute([$slug]);
        $group = $stmt->fetch();

        if (!$group) {
            return $this->twig->render(
                $response->withStatus(404),
                '404.html.twig',
                ['title' => 'Group not found', 'back' => ['href' => '/groups', 'label' => 'Browse groups']]
            );
        }

        $id = (int) $group['id'];

        $membersStmt = $this->db->prepare("
            SELECT u.id, u.name, gm.joined_at,
                   COUNT(r.id) AS rsvp_count
            FROM group_members gm
            JOIN users u ON u.id = gm.user_id
            LEFT JOIN group_events ge ON ge.group_id = gm.group_id
            LEFT JOIN event_rsvps r ON r.user_id = gm.user_id AND r.event_id = ge.id
            WHERE gm.group_id = ?
            GROUP BY gm.user_id, u.name, gm.joined_at
            ORDER BY CASE WHEN gm.user_id = ? THEN 0 ELSE 1 END ASC, gm.joined_at ASC
        ");
        $membersStmt->execute([$id, (int) $group['creator_id']]);
        $rows = $membersStmt->fetchAll();

        $now = new \DateTimeImmutable();
        $members = array_map(function (array $row) use ($now): array {
            $joined  = new \DateTimeImmutable($row['joined_at']);
            $diff    = $joined->diff($now);
            $years   = (int) $diff->y;
            $months  = (int) $diff->m;

            if ($years >= 1) {
                $duration = $years === 1 ? '1 year' : "{$years} years";
            } elseif ($months >= 1) {
                $duration = $months === 1 ? '1 month' : "{$months} months";
            } else {
                $duration = 'Less than a month';
            }

            $row['member_duration'] = $duration;
            return $row;
        }, $rows);

        return $this->twig->render($response, 'groups/members.html.twig', [
            'group'   => $group,
            'members' => $members,
        ]);
    }
}
