<?php
declare(strict_types=1);

namespace App\Action\Discussions;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

final class DiscussionDeleteAction
{
    use RedirectTrait;

    public function __construct(
        private Twig     $twig,
        private PDO      $db,
        private Messages $flash,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $slug    = $args['slug'];
        $topicId = (int) $args['topic_id'];
        $user    = $request->getAttribute('user');

        $stmt = $this->db->prepare(
            'SELECT id, slug, creator_id FROM user_groups WHERE slug = ?'
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

        $groupId = (int) $group['id'];

        $topicStmt = $this->db->prepare(
            'SELECT id, user_id FROM group_discussion_topics WHERE id = ? AND group_id = ?'
        );
        $topicStmt->execute([$topicId, $groupId]);
        $topic = $topicStmt->fetch();

        if (!$topic) {
            return $this->twig->render(
                $response->withStatus(404),
                '404.html.twig',
                ['title' => 'Topic not found', 'back' => ['href' => '/groups/' . $slug . '/discussions', 'label' => 'Discussions']]
            );
        }

        $isAuthor  = ((int) $topic['user_id'] === (int) $user['id']);
        $isCreator = ((int) $group['creator_id'] === (int) $user['id']);

        if (!$isAuthor && !$isCreator) {
            $this->flash->addMessage('error', 'You do not have permission to delete this topic.');
            return $this->redirect($response, $request, '/groups/' . $slug . '/discussions');
        }

        $this->db->prepare(
            'DELETE FROM group_discussion_topics WHERE id = ?'
        )->execute([$topicId]);

        $this->flash->addMessage('success', 'Topic deleted.');
        return $this->redirect($response, $request, '/groups/' . $slug . '/discussions');
    }
}
