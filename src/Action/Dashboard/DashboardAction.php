<?php
declare(strict_types=1);

namespace App\Action\Dashboard;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class DashboardAction
{
    public function __construct(
        private Twig $twig,
        private PDO  $db,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');

        $upcomingEvents = $this->db->prepare("
            SELECT e.id, e.title, e.event_date, e.event_time, e.location, e.meeting_url,
                   g.id AS group_id, g.name AS group_name
            FROM event_rsvps r
            JOIN group_events e ON e.id = r.event_id
            JOIN user_groups g  ON g.id = e.group_id
            WHERE r.user_id = ?
              AND (e.event_date > CURDATE() OR (e.event_date = CURDATE() AND e.event_time >= CURTIME()))
            ORDER BY e.event_date ASC, e.event_time ASC
        ");
        $upcomingEvents->execute([$user['id']]);

        $joinedGroups = $this->db->prepare("
            SELECT g.id, g.name, c.name AS city_name, c.state AS city_state,
                   COUNT(m2.id) AS member_count
            FROM group_members m
            JOIN user_groups g  ON g.id = m.group_id
            JOIN cities c       ON c.id = g.city_id
            LEFT JOIN group_members m2 ON m2.group_id = g.id
            WHERE m.user_id = ?
            GROUP BY g.id
            ORDER BY m.joined_at DESC
        ");
        $joinedGroups->execute([$user['id']]);

        $suggestedGroups = $this->db->prepare("
            SELECT g.id, g.name, c.name AS city_name, c.state AS city_state,
                   COUNT(m.id) AS member_count
            FROM user_groups g
            JOIN cities c ON c.id = g.city_id
            LEFT JOIN group_members m ON m.group_id = g.id
            WHERE g.id NOT IN (
                SELECT group_id FROM group_members WHERE user_id = ?
            ) AND c.id = ?
            GROUP BY g.id
            ORDER BY member_count DESC
            LIMIT 3
        ");
        $suggestedGroups->execute([$user['id'], $user['city_id']]);

        return $this->twig->render($response, 'dashboard.html.twig', [
            'upcomingEvents'  => $upcomingEvents->fetchAll(),
            'joinedGroups'    => $joinedGroups->fetchAll(),
            'suggestedGroups' => $suggestedGroups->fetchAll(),
        ]);
    }
}
