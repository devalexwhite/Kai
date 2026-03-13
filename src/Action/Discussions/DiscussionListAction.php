<?php
declare(strict_types=1);

namespace App\Action\Discussions;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class DiscussionListAction
{
    public function __construct(
        private Twig $twig,
        private PDO  $db,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];

        $stmt = $this->db->prepare(
            'SELECT id, slug, name, creator_id FROM user_groups WHERE slug = ?'
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

        $id        = (int) $group['id'];
        $user      = $request->getAttribute('user');
        $isMember  = false;
        $isCreator = false;

        if ($user) {
            $memberStmt = $this->db->prepare(
                'SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?'
            );
            $memberStmt->execute([$id, $user['id']]);
            $isMember  = (bool) $memberStmt->fetch();
            $isCreator = ((int) $group['creator_id'] === (int) $user['id']);
        }

        $page    = max(1, (int) ($request->getQueryParams()['page'] ?? 1));
        $perPage = 15;
        $offset  = ($page - 1) * $perPage;

        $countStmt = $this->db->prepare(
            'SELECT COUNT(*) FROM group_discussion_topics WHERE group_id = ?'
        );
        $countStmt->execute([$id]);
        $total      = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));

        $topicsStmt = $this->db->prepare("
            SELECT t.id, t.title, t.created_at,
                   u.id AS author_id, u.name AS author_name,
                   COUNT(r.id) AS reply_count
            FROM group_discussion_topics t
            JOIN users u ON u.id = t.user_id
            LEFT JOIN group_discussion_replies r ON r.topic_id = t.id
            WHERE t.group_id = ?
            GROUP BY t.id
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $topicsStmt->bindValue(1, $id, \PDO::PARAM_INT);
        $topicsStmt->bindValue(2, $perPage, \PDO::PARAM_INT);
        $topicsStmt->bindValue(3, $offset, \PDO::PARAM_INT);
        $topicsStmt->execute();
        $topics = $topicsStmt->fetchAll();

        return $this->twig->render($response, 'discussions/list.html.twig', [
            'group'      => $group,
            'topics'     => $topics,
            'isMember'   => $isMember,
            'isCreator'  => $isCreator,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
        ]);
    }
}
