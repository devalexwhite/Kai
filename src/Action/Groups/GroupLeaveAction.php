<?php
declare(strict_types=1);

namespace App\Action\Groups;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;

class GroupLeaveAction
{
    use RedirectTrait;

    public function __construct(
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

        if (!$group || (int) $group['creator_id'] === (int) $user['id']) {
            return $this->redirect($response, $request, '/groups/' . $slug);
        }

        $this->db->prepare(
            'DELETE FROM group_members WHERE group_id = ? AND user_id = ?'
        )->execute([(int) $group['id'], (int) $user['id']]);

        $this->flash->addMessage('success', 'You left ' . $group['name'] . '.');
        return $this->redirect($response, $request, '/groups/' . $slug);
    }
}
