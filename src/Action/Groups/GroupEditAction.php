<?php
declare(strict_types=1);

namespace App\Action\Groups;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class GroupEditAction
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

        $stmt = $this->db->prepare(
            'SELECT id, slug, name, description, city_id, creator_id FROM user_groups WHERE slug = ?'
        );
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
            $this->flash->addMessage('error', 'You do not have permission to edit this group.');
            return $this->redirect($response, $request, '/groups/' . $slug);
        }

        $id = (int) $group['id'];

        $preselectedId = (int) $group['city_id'];
        $cityStmt = $this->db->prepare('SELECT id, name, state FROM cities WHERE id = ?');
        $cityStmt->execute([$preselectedId]);
        $preselected = $cityStmt->fetch() ?: null;

        $cities = $this->db->query('SELECT id, name, state FROM cities ORDER BY state, name')->fetchAll();

        $linksStmt = $this->db->prepare(
            'SELECT id, title, url FROM group_links WHERE group_id = ? ORDER BY created_at ASC'
        );
        $linksStmt->execute([$id]);
        $links = $linksStmt->fetchAll();

        $tagsStmt = $this->db->prepare('
            SELECT t.id, t.name
            FROM tags t
            JOIN group_tags gt ON gt.tag_id = t.id
            WHERE gt.group_id = ?
            ORDER BY gt.created_at ASC
        ');
        $tagsStmt->execute([$id]);
        $tags = $tagsStmt->fetchAll();

        return $this->twig->render($response, 'groups/edit.html.twig', [
            'group'       => $group,
            'preselected' => $preselected,
            'cities'      => $cities,
            'links'       => $links,
            'tags'        => $tags,
            'old'         => [
                'name'        => $group['name'],
                'description' => $group['description'],
                'city_id'     => (string) $group['city_id'],
            ],
        ]);
    }
}
