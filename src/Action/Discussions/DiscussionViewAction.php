<?php
declare(strict_types=1);

namespace App\Action\Discussions;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class DiscussionViewAction
{
    public function __construct(
        private Twig $twig,
        private PDO  $db,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $groupId = (int) $args['id'];
        $topicId = (int) $args['topic_id'];

        $stmt = $this->db->prepare(
            'SELECT id, name, creator_id FROM user_groups WHERE id = ?'
        );
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();

        if (!$group) {
            return $this->twig->render(
                $response->withStatus(404),
                '404.html.twig',
                ['title' => 'Group not found', 'back' => ['href' => '/groups', 'label' => 'Browse groups']]
            );
        }

        $topicStmt = $this->db->prepare("
            SELECT t.id, t.title, t.body, t.created_at,
                   u.id AS author_id, u.name AS author_name
            FROM group_discussion_topics t
            JOIN users u ON u.id = t.user_id
            WHERE t.id = ? AND t.group_id = ?
        ");
        $topicStmt->execute([$topicId, $groupId]);
        $topic = $topicStmt->fetch();

        if (!$topic) {
            return $this->twig->render(
                $response->withStatus(404),
                '404.html.twig',
                ['title' => 'Topic not found', 'back' => ['href' => '/groups/' . $groupId . '/discussions', 'label' => 'Discussions']]
            );
        }

        $user      = $request->getAttribute('user');
        $isMember  = false;
        $isCreator = false;

        if ($user) {
            $memberStmt = $this->db->prepare(
                'SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?'
            );
            $memberStmt->execute([$groupId, $user['id']]);
            $isMember  = (bool) $memberStmt->fetch();
            $isCreator = ((int) $group['creator_id'] === (int) $user['id']);
        }

        $page    = max(1, (int) ($request->getQueryParams()['page'] ?? 1));
        $perPage = 15;
        $offset  = ($page - 1) * $perPage;

        $countStmt = $this->db->prepare(
            'SELECT COUNT(*) FROM group_discussion_replies WHERE topic_id = ?'
        );
        $countStmt->execute([$topicId]);
        $total      = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));

        $repliesStmt = $this->db->prepare("
            SELECT r.id, r.body, r.created_at,
                   u.id AS author_id, u.name AS author_name
            FROM group_discussion_replies r
            JOIN users u ON u.id = r.user_id
            WHERE r.topic_id = ?
            ORDER BY r.created_at ASC
            LIMIT ? OFFSET ?
        ");
        $repliesStmt->bindValue(1, $topicId, \PDO::PARAM_INT);
        $repliesStmt->bindValue(2, $perPage, \PDO::PARAM_INT);
        $repliesStmt->bindValue(3, $offset, \PDO::PARAM_INT);
        $repliesStmt->execute();
        $replies = $repliesStmt->fetchAll();

        return $this->twig->render($response, 'discussions/view.html.twig', [
            'group'      => $group,
            'topic'      => $topic,
            'replies'    => $replies,
            'isMember'   => $isMember,
            'isCreator'  => $isCreator,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
        ]);
    }
}
