<?php
declare(strict_types=1);

namespace App\Action\Groups;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class GroupLinkDeleteAction
{
    use RedirectTrait;

    public function __construct(
        private Twig     $twig,
        private PDO      $db,
        private Messages $flash,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $slug   = $args['slug'];
        $linkId = (int) $args['link_id'];
        $user   = $request->getAttribute('user');

        $stmt = $this->db->prepare('SELECT id, slug, creator_id FROM user_groups WHERE slug = ?');
        $stmt->execute([$slug]);
        $group = $stmt->fetch();

        if (!$group || (int) $group['creator_id'] !== (int) $user['id']) {
            $this->flash->addMessage('error', 'Group not found or you do not have permission to delete links.');
            return $this->redirect($response, $request, '/groups/' . $slug . '/edit');
        }

        $groupId = (int) $group['id'];

        $linkStmt = $this->db->prepare('SELECT id FROM group_links WHERE id = ? AND group_id = ?');
        $linkStmt->execute([$linkId, $groupId]);

        if (!$linkStmt->fetch()) {
            $this->flash->addMessage('error', 'Link not found.');
            return $this->redirect($response, $request, '/groups/' . $slug . '/edit');
        }

        $this->db->prepare('DELETE FROM group_links WHERE id = ?')->execute([$linkId]);

        $isHtmx = $request->getHeaderLine('HX-Request') === 'true';

        if ($isHtmx) {
            $linksStmt = $this->db->prepare(
                'SELECT id, title, url FROM group_links WHERE group_id = ? ORDER BY created_at ASC'
            );
            $linksStmt->execute([$groupId]);
            return $this->twig->render($response, 'partials/group_links_edit.html.twig', [
                'group_slug' => $slug,
                'links'      => $linksStmt->fetchAll(),
                'errors'     => [],
                'old'        => [],
            ]);
        }

        $this->flash->addMessage('success', 'Link removed.');
        return $this->redirect($response, $request, '/groups/' . $slug . '/edit');
    }
}
