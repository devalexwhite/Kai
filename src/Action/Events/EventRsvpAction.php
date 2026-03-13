<?php
declare(strict_types=1);

namespace App\Action\Events;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;

class EventRsvpAction
{
    use RedirectTrait;

    public function __construct(
        private PDO      $db,
        private Messages $flash,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id   = (int) $args['id'];
        $slug = $args['slug'];
        $user = $request->getAttribute('user');

        $stmt = $this->db->prepare('
            SELECT e.id, e.title, g.slug AS group_slug
            FROM group_events e
            JOIN user_groups g ON g.id = e.group_id
            WHERE e.id = ? AND g.slug = ?
        ');
        $stmt->execute([$id, $slug]);
        $event = $stmt->fetch();

        if (!$event) {
            return $this->redirect($response, $request, '/groups');
        }

        $body   = (array) $request->getParsedBody();
        $action = $body['rsvp_action'] ?? '';

        if ($action === 'rsvp') {
            $this->db->prepare(
                'INSERT IGNORE INTO event_rsvps (event_id, user_id) VALUES (?, ?)'
            )->execute([$id, $user['id']]);
            $this->flash->addMessage('success', "You're going to " . $event['title'] . '!');
        } elseif ($action === 'cancel') {
            $this->db->prepare(
                'DELETE FROM event_rsvps WHERE event_id = ? AND user_id = ?'
            )->execute([$id, $user['id']]);
            $this->flash->addMessage('success', 'Your RSVP has been cancelled.');
        }

        return $this->redirect($response, $request, '/groups/' . $event['group_slug'] . '/events/' . $id);
    }
}
