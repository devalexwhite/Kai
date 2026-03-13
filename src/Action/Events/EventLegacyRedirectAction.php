<?php
declare(strict_types=1);

namespace App\Action\Events;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class EventLegacyRedirectAction
{
    public function __construct(private PDO $db) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        $stmt = $this->db->prepare('
            SELECT e.id, g.slug AS group_slug
            FROM group_events e
            JOIN user_groups g ON g.id = e.group_id
            WHERE e.id = ?
        ');
        $stmt->execute([$id]);
        $event = $stmt->fetch();

        if (!$event) {
            return $response->withStatus(404);
        }

        return $response
            ->withHeader('Location', '/groups/' . $event['group_slug'] . '/events/' . $event['id'])
            ->withStatus(301);
    }
}
