<?php
declare(strict_types=1);

namespace App\Action\Discussions;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

final class ReplyCreateAction
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

        $groupId = (int) $group['id'];

        $topicStmt = $this->db->prepare(
            'SELECT id FROM group_discussion_topics WHERE id = ? AND group_id = ?'
        );
        $topicStmt->execute([$topicId, $groupId]);
        if (!$topicStmt->fetch()) {
            return $this->twig->render(
                $response->withStatus(404),
                '404.html.twig',
                ['title' => 'Topic not found', 'back' => ['href' => '/groups/' . $slug . '/discussions', 'label' => 'Discussions']]
            );
        }

        $isCreator = ((int) $group['creator_id'] === (int) $user['id']);

        $memberStmt = $this->db->prepare(
            'SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?'
        );
        $memberStmt->execute([$groupId, $user['id']]);
        $isMember = (bool) $memberStmt->fetch();

        if (!$isMember && !$isCreator) {
            $this->flash->addMessage('error', 'You must be a group member to reply.');
            return $this->redirect($response, $request, '/groups/' . $slug . '/discussions/' . $topicId);
        }

        $body   = (array) $request->getParsedBody();
        $text   = trim($body['body'] ?? '');
        $errors = [];

        if ($text === '') {
            $errors['body'] = 'Reply cannot be empty.';
        } elseif (mb_strlen($text) > 10000) {
            $errors['body'] = 'Reply must be under 10,000 characters.';
        }

        if (!empty($errors)) {
            $this->flash->addMessage('error', implode(' ', $errors));
            return $this->redirect($response, $request, '/groups/' . $slug . '/discussions/' . $topicId);
        }

        $this->db->prepare(
            'INSERT INTO group_discussion_replies (topic_id, user_id, body) VALUES (?, ?, ?)'
        )->execute([$topicId, $user['id'], $text]);

        return $this->redirect($response, $request, '/groups/' . $slug . '/discussions/' . $topicId);
    }
}
