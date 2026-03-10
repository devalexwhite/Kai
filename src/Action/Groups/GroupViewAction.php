<?php
declare(strict_types=1);

namespace App\Action\Groups;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class GroupViewAction
{
    public function __construct(
        private Twig $twig,
        private PDO  $db,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        $stmt = $this->db->prepare("
            SELECT g.id, g.name, g.description, g.creator_id, g.created_at,
                   c.name AS city_name, c.state AS city_state,
                   u.name AS creator_name
            FROM user_groups g
            JOIN cities c ON c.id = g.city_id
            JOIN users u  ON u.id = g.creator_id
            WHERE g.id = ?
        ");
        $stmt->execute([$id]);
        $group = $stmt->fetch();

        if (!$group) {
            return $this->twig->render(
                $response->withStatus(404),
                '404.html.twig',
                ['title' => 'Group not found', 'back' => ['href' => '/groups', 'label' => 'Browse groups']]
            );
        }

        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ?');
        $countStmt->execute([$id]);
        $memberCount = (int) $countStmt->fetchColumn();

        $user      = $request->getAttribute('user');
        $isMember  = false;
        $isCreator = false;

        if ($user) {
            $memberStmt = $this->db->prepare(
                'SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?'
            );
            $memberStmt->execute([$id, $user['id']]);
            $isMember  = (bool) $memberStmt->fetch();
            $isCreator = ((int) $group['creator_id'] === (int) $user['id']);
        }

        $today = date('Y-m-d');
        $now   = date('H:i');

        $eventsStmt = $this->db->prepare("
            SELECT e.id, e.title, e.event_date, e.event_time, e.location, e.meeting_url,
                   COUNT(r.id) AS rsvp_count
            FROM group_events e
            LEFT JOIN event_rsvps r ON r.event_id = e.id
            WHERE e.group_id = ?
            GROUP BY e.id
            ORDER BY e.event_date ASC, e.event_time ASC
        ");
        $eventsStmt->execute([$id]);
        $allEvents = $eventsStmt->fetchAll();

        $upcomingEvents = [];
        $pastEvents     = [];

        foreach ($allEvents as $ev) {
            if ($ev['event_date'] > $today || ($ev['event_date'] === $today && $ev['event_time'] >= $now)) {
                $upcomingEvents[] = $ev;
            } else {
                $pastEvents[] = $ev;
            }
        }
        $pastEvents = array_reverse($pastEvents);

        $linksStmt = $this->db->prepare(
            'SELECT id, title, url FROM group_links WHERE group_id = ? ORDER BY created_at ASC'
        );
        $linksStmt->execute([$id]);
        $links = $linksStmt->fetchAll();

        return $this->twig->render($response, 'groups/view.html.twig', [
            'group'          => $group,
            'memberCount'    => $memberCount,
            'isMember'       => $isMember,
            'isCreator'      => $isCreator,
            'upcomingEvents' => $upcomingEvents,
            'pastEvents'     => $pastEvents,
            'pastCount'      => count($pastEvents),
            'showPast'       => isset($request->getQueryParams()['show_past']),
            'links'          => $links,
        ]);
    }
}
