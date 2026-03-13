<?php
declare(strict_types=1);

namespace App\Action\Events;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class EventEditAction
{
    use RedirectTrait;

    public function __construct(
        private Twig     $twig,
        private PDO      $db,
        private Messages $flash,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id   = (int) $args['id'];
        $slug = $args['slug'];
        $user = $request->getAttribute('user');

        $stmt = $this->db->prepare("
            SELECT e.id, e.group_id, e.creator_id, e.title, e.description,
                   e.event_date, e.event_time, e.location, e.meeting_url,
                   g.name AS group_name, g.slug AS group_slug, g.creator_id AS group_creator_id
            FROM group_events e
            JOIN user_groups g ON g.id = e.group_id
            WHERE e.id = ? AND g.slug = ?
        ");
        $stmt->execute([$id, $slug]);
        $event = $stmt->fetch();

        if (!$event) {
            return $this->twig->render(
                $response->withStatus(404),
                '404.html.twig',
                ['title' => 'Event not found', 'back' => ['href' => '/groups', 'label' => 'Browse groups']]
            );
        }

        $isEventOwner = ((int) $event['creator_id'] === (int) $user['id']);
        $isGroupOwner = ((int) $event['group_creator_id'] === (int) $user['id']);

        if (!$isEventOwner && !$isGroupOwner) {
            $this->flash->addMessage('error', 'You do not have permission to edit this event.');
            return $this->redirect($response, $request, '/groups/' . $slug . '/events/' . $id);
        }

        return $this->twig->render($response, 'events/edit.html.twig', [
            'event' => $event,
            'slug'  => $slug,
            'old'   => [
                'title'       => $event['title'],
                'description' => $event['description'],
                'event_date'  => $event['event_date'],
                'event_time'  => $event['event_time'],
                'location'    => $event['location'],
                'meeting_url' => $event['meeting_url'],
            ],
        ]);
    }
}
