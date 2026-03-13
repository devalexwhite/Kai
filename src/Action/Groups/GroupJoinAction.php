<?php
declare(strict_types=1);

namespace App\Action\Groups;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class GroupJoinAction
{
    use RedirectTrait;

    public function __construct(
        private Twig $twig,
        private PDO  $db,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        $user = $request->getAttribute('user');

        $groupStmt = $this->db->prepare('SELECT id, slug FROM user_groups WHERE slug = ?');
        $groupStmt->execute([$slug]);
        $group = $groupStmt->fetch();

        if (!$group) {
            return $this->redirect($response, $request, '/groups');
        }

        $this->db->prepare(
            'INSERT IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)'
        )->execute([(int) $group['id'], (int) $user['id']]);

        if ($request->getHeaderLine('HX-Request') === 'true') {
            return $this->twig->render($response, 'partials/group_join_result.html.twig', [
                'group_slug' => $slug,
            ]);
        }

        return $this->redirect($response, $request, '/groups/' . $slug);
    }
}
