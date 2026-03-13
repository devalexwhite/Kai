<?php
declare(strict_types=1);

namespace App\Action\Discussions;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

final class ReplyDeleteAction
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
        $replyId = (int) $args['reply_id'];
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

        $replyStmt = $this->db->prepare(
            'SELECT id, user_id FROM group_discussion_replies WHERE id = ? AND topic_id = ?'
        );
        $replyStmt->execute([$replyId, $topicId]);
        $reply = $replyStmt->fetch();

        if (!$reply) {
            return $this->twig->render(
                $response->withStatus(404),
                '404.html.twig',
                ['title' => 'Reply not found', 'back' => ['href' => '/groups/' . $slug . '/discussions/' . $topicId, 'label' => 'Discussion']]
            );
        }

        $isAuthor  = ((int) $reply['user_id'] === (int) $user['id']);
        $isCreator = ((int) $group['creator_id'] === (int) $user['id']);

        if (!$isAuthor && !$isCreator) {
            $this->flash->addMessage('error', 'You do not have permission to delete this reply.');
            return $this->redirect($response, $request, '/groups/' . $slug . '/discussions/' . $topicId);
        }

        $this->db->prepare(
            'DELETE FROM group_discussion_replies WHERE id = ?'
        )->execute([$replyId]);

        $isHtmx = $request->getHeaderLine('HX-Request') === 'true';
        if ($isHtmx) {
            $countStmt = $this->db->prepare(
                'SELECT COUNT(*) FROM group_discussion_replies WHERE topic_id = ?'
            );
            $countStmt->execute([$topicId]);
            $remaining = (int) $countStmt->fetchColumn();
            $label     = $remaining === 1 ? 'reply' : 'replies';

            $body = '<h2 id="replies-count" class="discussion-replies__heading" hx-swap-oob="true">'
                  . $remaining . ' ' . $label
                  . '</h2>';

            $response->getBody()->write($body);
            return $response->withStatus(200)->withHeader('Content-Type', 'text/html');
        }

        return $this->redirect($response, $request, '/groups/' . $slug . '/discussions/' . $topicId);
    }
}
