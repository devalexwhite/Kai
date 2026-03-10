<?php
declare(strict_types=1);

namespace App\Action\Groups;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class GroupLinkCreateAction
{
    public function __construct(
        private Twig $twig,
        private PDO  $db,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id   = (int) $args['id'];
        $user = $request->getAttribute('user');

        $stmt = $this->db->prepare('SELECT id, name, creator_id FROM user_groups WHERE id = ?');
        $stmt->execute([$id]);
        $group = $stmt->fetch();

        if (!$group) {
            return $this->twig->render(
                $response->withStatus(404),
                '404.html.twig',
                ['title' => 'Group not found', 'back' => ['href' => '/groups', 'label' => 'Browse groups']]
            );
        }

        if ((int) $group['creator_id'] !== (int) $user['id']) {
            return $this->twig->render(
                $response->withStatus(403),
                '404.html.twig',
                ['title' => 'Forbidden', 'back' => ['href' => '/groups/' . $id, 'label' => 'Back to group']]
            );
        }

        return $this->twig->render($response, 'groups/link_add.html.twig', [
            'group'  => $group,
            'errors' => [],
            'old'    => [],
        ]);
    }
}
