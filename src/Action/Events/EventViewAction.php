<?php
declare(strict_types=1);

namespace App\Action\Events;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class EventViewAction
{
    public function __construct(
        private Twig $twig,
        private PDO  $db,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        $stmt = $this->db->prepare("
            SELECT e.id, e.group_id, e.creator_id, e.title, e.description,
                   e.event_date, e.event_time, e.location, e.meeting_url,
                   g.name AS group_name, g.creator_id AS group_creator_id
            FROM group_events e
            JOIN user_groups g ON g.id = e.group_id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $event = $stmt->fetch();

        if (!$event) {
            return $this->twig->render(
                $response->withStatus(404),
                '404.html.twig',
                ['title' => 'Event not found', 'back' => ['href' => '/groups', 'label' => 'Browse groups']]
            );
        }

        $rsvpCountStmt = $this->db->prepare('SELECT COUNT(*) FROM event_rsvps WHERE event_id = ?');
        $rsvpCountStmt->execute([$id]);
        $rsvpCount = (int) $rsvpCountStmt->fetchColumn();

        $user         = $request->getAttribute('user');
        $isAttendee   = false;
        $isMember     = false;
        $isEventOwner = false;
        $isGroupOwner = false;

        if ($user) {
            $rsvpStmt = $this->db->prepare('SELECT 1 FROM event_rsvps WHERE event_id = ? AND user_id = ?');
            $rsvpStmt->execute([$id, $user['id']]);
            $isAttendee = (bool) $rsvpStmt->fetch();

            $memberStmt = $this->db->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
            $memberStmt->execute([$event['group_id'], $user['id']]);
            $isMember = (bool) $memberStmt->fetch();

            $isEventOwner = ((int) $event['creator_id'] === (int) $user['id']);
            $isGroupOwner = ((int) $event['group_creator_id'] === (int) $user['id']);
        }

        $attendeesStmt = $this->db->prepare("
            SELECT u.name,
                   CASE WHEN gm.id IS NOT NULL THEN 1 ELSE 0 END AS is_member
            FROM event_rsvps er
            JOIN users u ON u.id = er.user_id
            LEFT JOIN group_members gm ON gm.group_id = ? AND gm.user_id = er.user_id
            WHERE er.event_id = ?
            ORDER BY er.created_at ASC
        ");
        $attendeesStmt->execute([$event['group_id'], $id]);

        $today  = date('Y-m-d');
        $now    = date('H:i');
        $isPast = $event['event_date'] < $today ||
                  ($event['event_date'] === $today && $event['event_time'] < $now);

        return $this->twig->render($response, 'events/view.html.twig', [
            'event'        => $event,
            'rsvpCount'    => $rsvpCount,
            'isAttendee'   => $isAttendee,
            'isMember'     => $isMember,
            'isEventOwner' => $isEventOwner,
            'isGroupOwner' => $isGroupOwner,
            'isPast'       => $isPast,
            'attendees'    => $attendeesStmt->fetchAll(),
        ]);
    }
}
