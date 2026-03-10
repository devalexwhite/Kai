<?php
declare(strict_types=1);

namespace App\Action\Groups;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;

class GroupDeleteAction
{
    use RedirectTrait;

    public function __construct(
        private PDO      $db,
        private Messages $flash,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id   = (int) $args['id'];
        $user = $request->getAttribute('user');

        $stmt = $this->db->prepare('SELECT id, name, creator_id FROM user_groups WHERE id = ?');
        $stmt->execute([$id]);
        $group = $stmt->fetch();

        if (!$group || (int) $group['creator_id'] !== (int) $user['id']) {
            $this->flash->addMessage('error', 'Group not found or you do not have permission to delete it.');
            return $this->redirect($response, $request, '/groups');
        }

        $this->db->prepare('DELETE FROM user_groups WHERE id = ?')->execute([$id]);

        $this->flash->addMessage('success', 'Group deleted.');
        return $this->redirect($response, $request, '/groups');
    }
}
