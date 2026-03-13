<?php
declare(strict_types=1);

namespace App\Action\Events;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class EventCreateAction
{
    use RedirectTrait;

    public function __construct(
        private Twig     $twig,
        private PDO      $db,
        private Messages $flash,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        $user = $request->getAttribute('user');

        $stmt = $this->db->prepare('SELECT id, slug, name, creator_id FROM user_groups WHERE slug = ?');
        $stmt->execute([$slug]);
        $group = $stmt->fetch();

        if (!$group) {
            return $this->redirect($response, $request, '/groups');
        }

        if ((int) $group['creator_id'] !== (int) $user['id']) {
            $this->flash->addMessage('error', 'Only the group owner can create events.');
            return $this->redirect($response, $request, '/groups/' . $group['slug']);
        }

        return $this->twig->render($response, 'events/create.html.twig', [
            'group' => $group,
        ]);
    }
}
