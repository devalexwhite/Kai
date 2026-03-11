<?php
declare(strict_types=1);

namespace App\Action\Events;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class BrowseEventsAction
{
    public function __construct(
        private Twig $twig,
        private PDO  $db,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $user            = $request->getAttribute('user');
        $myRsvpdEvents   = [];
        $myGroupEvents   = [];
        $cityEvents      = [];

        if ($user !== null) {
            $stmt = $this->db->prepare("
                SELECT e.id, e.title, e.event_date, e.event_time, e.location, e.meeting_url,
                       g.id AS group_id, g.name AS group_name
                FROM event_rsvps r
                JOIN group_events e ON e.id = r.event_id
                JOIN user_groups g  ON g.id = e.group_id
                WHERE r.user_id = ?
                  AND (e.event_date > CURDATE() OR (e.event_date = CURDATE() AND e.event_time >= CURTIME()))
                ORDER BY e.event_date ASC, e.event_time ASC
            ");
            $stmt->execute([$user['id']]);
            $myRsvpdEvents = $stmt->fetchAll();

            $stmt = $this->db->prepare("
                SELECT e.id, e.title, e.event_date, e.event_time, e.location, e.meeting_url,
                       g.id AS group_id, g.name AS group_name
                FROM group_events e
                JOIN user_groups g ON g.id = e.group_id
                WHERE (
                    EXISTS (SELECT 1 FROM group_members gm WHERE gm.group_id = e.group_id AND gm.user_id = ?)
                    OR g.creator_id = ?
                )
                  AND NOT EXISTS (SELECT 1 FROM event_rsvps r WHERE r.event_id = e.id AND r.user_id = ?)
                  AND (e.event_date > CURDATE() OR (e.event_date = CURDATE() AND e.event_time >= CURTIME()))
                  AND e.event_date <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
                ORDER BY e.event_date ASC, e.event_time ASC
            ");
            $stmt->execute([$user['id'], $user['id'], $user['id']]);
            $myGroupEvents = $stmt->fetchAll();

            $stmt = $this->db->prepare("
                SELECT e.id, e.title, e.event_date, e.event_time, e.location, e.meeting_url,
                       g.id AS group_id, g.name AS group_name
                FROM group_events e
                JOIN user_groups g ON g.id = e.group_id
                WHERE g.city_id = ?
                  AND g.creator_id != ?
                  AND NOT EXISTS (SELECT 1 FROM group_members gm WHERE gm.group_id = g.id AND gm.user_id = ?)
                  AND NOT EXISTS (SELECT 1 FROM event_rsvps r WHERE r.event_id = e.id AND r.user_id = ?)
                  AND (e.event_date > CURDATE() OR (e.event_date = CURDATE() AND e.event_time >= CURTIME()))
                  AND e.event_date <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
                ORDER BY e.event_date ASC, e.event_time ASC
            ");
            $stmt->execute([$user['city_id'], $user['id'], $user['id'], $user['id']]);
            $cityEvents = $stmt->fetchAll();
        }

        return $this->twig->render($response, 'events/browse.html.twig', [
            'myRsvpdEvents' => $myRsvpdEvents,
            'myGroupEvents' => $myGroupEvents,
            'cityEvents'    => $cityEvents,
        ]);
    }
}
