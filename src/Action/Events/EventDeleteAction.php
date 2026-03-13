<?php
declare(strict_types=1);

namespace App\Action\Events;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;

class EventDeleteAction
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

        $stmt = $this->db->prepare("
            SELECT e.id, e.creator_id, g.creator_id AS group_creator_id, g.slug AS group_slug
            FROM group_events e
            JOIN user_groups g ON g.id = e.group_id
            WHERE e.id = ? AND g.slug = ?
        ");
        $stmt->execute([$id, $slug]);
        $event = $stmt->fetch();

        if (!$event ||
            ((int) $event['creator_id'] !== (int) $user['id'] &&
             (int) $event['group_creator_id'] !== (int) $user['id'])) {
            $this->flash->addMessage('error', 'Event not found or you do not have permission to delete it.');
            return $this->redirect($response, $request, '/groups');
        }

        $this->db->prepare('DELETE FROM group_events WHERE id = ?')->execute([$id]);

        $this->flash->addMessage('success', 'Event deleted.');
        return $this->redirect($response, $request, '/groups/' . $event['group_slug']);
    }
}
