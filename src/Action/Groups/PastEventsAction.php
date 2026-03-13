<?php
declare(strict_types=1);

namespace App\Action\Groups;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class PastEventsAction
{
    use RedirectTrait;

    public function __construct(
        private Twig $twig,
        private PDO  $db,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];

        if ($request->getHeaderLine('HX-Request') !== 'true') {
            return $this->redirect($response, $request, '/groups/' . $slug . '?show_past=1');
        }

        $groupStmt = $this->db->prepare('SELECT id FROM user_groups WHERE slug = ?');
        $groupStmt->execute([$slug]);
        $group = $groupStmt->fetch();

        if (!$group) {
            return $response->withStatus(404);
        }

        $groupId = (int) $group['id'];
        $today   = date('Y-m-d');
        $now     = date('H:i');

        $stmt = $this->db->prepare("
            SELECT e.id, e.title, e.event_date, e.event_time, e.location, e.meeting_url,
                   COUNT(r.id) AS rsvp_count
            FROM group_events e
            LEFT JOIN event_rsvps r ON r.event_id = e.id
            WHERE e.group_id = ?
              AND (e.event_date < ? OR (e.event_date = ? AND e.event_time < ?))
            GROUP BY e.id
            ORDER BY e.event_date DESC, e.event_time DESC
        ");
        $stmt->execute([$groupId, $today, $today, $now]);

        return $this->twig->render($response, 'partials/past_events.html.twig', [
            'pastEvents' => $stmt->fetchAll(),
            'group'      => ['slug' => $slug],
        ]);
    }
}
