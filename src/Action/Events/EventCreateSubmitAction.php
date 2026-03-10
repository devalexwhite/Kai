<?php
declare(strict_types=1);

namespace App\Action\Events;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class EventCreateSubmitAction
{
    use RedirectTrait;

    public function __construct(
        private Twig     $twig,
        private PDO      $db,
        private Messages $flash,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $groupId = (int) ($request->getQueryParams()['group_id'] ?? 0);
        $user    = $request->getAttribute('user');

        $stmt = $this->db->prepare('SELECT id, name, creator_id FROM user_groups WHERE id = ?');
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();

        if (!$group || (int) $group['creator_id'] !== (int) $user['id']) {
            return $this->redirect($response, $request, '/groups');
        }

        $body       = (array) $request->getParsedBody();
        $title      = trim($body['title'] ?? '');
        $desc       = trim($body['description'] ?? '');
        $eventDate  = trim($body['event_date'] ?? '');
        $eventTime  = trim($body['event_time'] ?? '');
        $location   = trim($body['location'] ?? '');
        $meetingUrl = trim($body['meeting_url'] ?? '');
        $errors     = [];

        if (mb_strlen($title) < 2) {
            $errors['title'] = 'Event title must be at least 2 characters.';
        } elseif (mb_strlen($title) > 120) {
            $errors['title'] = 'Event title must be under 120 characters.';
        }

        if (mb_strlen($desc) > 2000) {
            $errors['description'] = 'Description must be under 2000 characters.';
        }

        if ($eventDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            $errors['event_date'] = 'Please enter a valid date.';
        }

        if ($eventTime === '' || !preg_match('/^\d{2}:\d{2}$/', $eventTime)) {
            $errors['event_time'] = 'Please enter a valid time.';
        }

        if (mb_strlen($location) > 200) {
            $errors['location'] = 'Location must be under 200 characters.';
        }

        if ($meetingUrl !== '') {
            $scheme = strtolower(substr($meetingUrl, 0, 8));
            if (!str_starts_with($scheme, 'http://') && !str_starts_with($scheme, 'https://')) {
                $errors['meeting_url'] = 'Meeting URL must start with http:// or https://.';
            } elseif (!filter_var($meetingUrl, FILTER_VALIDATE_URL)) {
                $errors['meeting_url'] = 'Please enter a valid URL.';
            }
        }

        if (empty($errors)) {
            $this->db->prepare("
                INSERT INTO group_events
                    (group_id, creator_id, title, description, event_date, event_time, location, meeting_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$groupId, $user['id'], $title, $desc, $eventDate, $eventTime, $location, $meetingUrl]);

            $eventId = (int) $this->db->lastInsertId();
            $this->flash->addMessage('success', 'Event created!');
            return $this->redirect($response, $request, '/events/' . $eventId);
        }

        return $this->twig->render($response, 'events/create.html.twig', [
            'group'  => $group,
            'errors' => $errors,
            'old'    => [
                'title'       => $title,
                'description' => $desc,
                'event_date'  => $eventDate,
                'event_time'  => $eventTime,
                'location'    => $location,
                'meeting_url' => $meetingUrl,
            ],
        ]);
    }
}
