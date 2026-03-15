<?php
declare(strict_types=1);

namespace App\Action\Groups;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

final class GroupTagRemoveAction
{
    use RedirectTrait;

    public function __construct(
        private readonly Twig     $twig,
        private readonly PDO      $db,
        private readonly Messages $flash,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $slug  = $args['slug'];
        $tagId = (int) $args['tag_id'];
        $user  = $request->getAttribute('user');

        $stmt = $this->db->prepare('SELECT id, slug, creator_id FROM user_groups WHERE slug = ?');
        $stmt->execute([$slug]);
        $group = $stmt->fetch();

        if (!$group || (int) $group['creator_id'] !== (int) $user['id']) {
            $this->flash->addMessage('error', 'Group not found or permission denied.');
            return $this->redirect($response, $request, '/groups/' . $slug . '/edit');
        }

        $groupId = (int) $group['id'];

        $this->db->prepare(
            'DELETE FROM group_tags WHERE group_id = ? AND tag_id = ?'
        )->execute([$groupId, $tagId]);

        $isHtmx = $request->getHeaderLine('HX-Request') === 'true';

        if ($isHtmx) {
            $tagsStmt = $this->db->prepare('
                SELECT t.id, t.name
                FROM tags t
                JOIN group_tags gt ON gt.tag_id = t.id
                WHERE gt.group_id = ?
                ORDER BY gt.created_at ASC
            ');
            $tagsStmt->execute([$groupId]);
            return $this->twig->render($response, 'partials/group_tags_edit.html.twig', [
                'group_slug' => $slug,
                'tags'       => $tagsStmt->fetchAll(),
                'error'      => null,
            ]);
        }

        $this->flash->addMessage('success', 'Tag removed.');
        return $this->redirect($response, $request, '/groups/' . $slug . '/edit');
    }
}
