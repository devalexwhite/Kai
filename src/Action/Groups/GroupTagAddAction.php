<?php
declare(strict_types=1);

namespace App\Action\Groups;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

final class GroupTagAddAction
{
    use RedirectTrait;

    public function __construct(
        private readonly Twig     $twig,
        private readonly PDO      $db,
        private readonly Messages $flash,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        $user = $request->getAttribute('user');

        $stmt = $this->db->prepare('SELECT id, slug, creator_id FROM user_groups WHERE slug = ?');
        $stmt->execute([$slug]);
        $group = $stmt->fetch();

        if (!$group) {
            return $this->twig->render(
                $response->withStatus(404),
                '404.html.twig',
                ['title' => 'Group not found', 'back' => ['href' => '/groups', 'label' => 'Browse groups']]
            );
        }

        if ((int) $group['creator_id'] !== (int) $user['id']) {
            $this->flash->addMessage('error', 'You do not have permission to add tags to this group.');
            return $this->redirect($response, $request, '/groups/' . $slug . '/edit');
        }

        $groupId = (int) $group['id'];
        $body    = (array) $request->getParsedBody();
        $raw     = trim($body['name'] ?? '');

        // Normalize: lowercase, strip invalid chars, trim edge hyphens/underscores
        $name = strtolower($raw);
        $name = (string) preg_replace('/[^a-z0-9_-]/', '', $name);
        $name = trim($name, '-_');

        $isHtmx = $request->getHeaderLine('HX-Request') === 'true';
        $error  = null;

        if ($name === '') {
            $error = 'Tag name is required.';
        } elseif (mb_strlen($name) > 30) {
            $error = 'Tag name must be 30 characters or fewer.';
        }

        if ($error === null) {
            $countStmt = $this->db->prepare('SELECT COUNT(*) FROM group_tags WHERE group_id = ?');
            $countStmt->execute([$groupId]);
            if ((int) $countStmt->fetchColumn() >= 5) {
                $error = 'Groups can have at most 5 tags.';
            }
        }

        if ($error === null) {
            $this->db->prepare('INSERT IGNORE INTO tags (name) VALUES (?)')->execute([$name]);

            $tagStmt = $this->db->prepare('SELECT id FROM tags WHERE name = ?');
            $tagStmt->execute([$name]);
            $tagId = (int) $tagStmt->fetchColumn();

            $this->db->prepare(
                'INSERT IGNORE INTO group_tags (group_id, tag_id) VALUES (?, ?)'
            )->execute([$groupId, $tagId]);
        }

        if ($isHtmx) {
            return $this->twig->render($response, 'partials/group_tags_edit.html.twig', [
                'group_slug' => $slug,
                'tags'       => $this->fetchTags($groupId),
                'error'      => $error,
            ]);
        }

        if ($error) {
            $this->flash->addMessage('error', $error);
        }
        return $this->redirect($response, $request, '/groups/' . $slug . '/edit');
    }

    private function fetchTags(int $groupId): array
    {
        $stmt = $this->db->prepare('
            SELECT t.id, t.name
            FROM tags t
            JOIN group_tags gt ON gt.tag_id = t.id
            WHERE gt.group_id = ?
            ORDER BY gt.created_at ASC
        ');
        $stmt->execute([$groupId]);
        return $stmt->fetchAll();
    }
}
