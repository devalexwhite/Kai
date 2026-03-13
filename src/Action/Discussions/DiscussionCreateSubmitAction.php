<?php
declare(strict_types=1);

namespace App\Action\Discussions;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

final class DiscussionCreateSubmitAction
{
    use RedirectTrait;

    public function __construct(
        private Twig     $twig,
        private PDO      $db,
        private Messages $flash,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id   = (int) $args['id'];
        $user = $request->getAttribute('user');

        $stmt = $this->db->prepare(
            'SELECT id, name, creator_id FROM user_groups WHERE id = ?'
        );
        $stmt->execute([$id]);
        $group = $stmt->fetch();

        if (!$group) {
            return $this->twig->render(
                $response->withStatus(404),
                '404.html.twig',
                ['title' => 'Group not found', 'back' => ['href' => '/groups', 'label' => 'Browse groups']]
            );
        }

        $isCreator = ((int) $group['creator_id'] === (int) $user['id']);

        $memberStmt = $this->db->prepare(
            'SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?'
        );
        $memberStmt->execute([$id, $user['id']]);
        $isMember = (bool) $memberStmt->fetch();

        if (!$isMember && !$isCreator) {
            $this->flash->addMessage('error', 'You must be a group member to start a discussion.');
            return $this->redirect($response, $request, '/groups/' . $id . '/discussions');
        }

        $body   = (array) $request->getParsedBody();
        $title  = trim($body['title'] ?? '');
        $text   = trim($body['body'] ?? '');
        $errors = [];

        if ($title === '') {
            $errors['title'] = 'Title is required.';
        } elseif (mb_strlen($title) > 255) {
            $errors['title'] = 'Title must be under 255 characters.';
        }

        if ($text === '') {
            $errors['body'] = 'Body is required.';
        } elseif (mb_strlen($text) > 10000) {
            $errors['body'] = 'Body must be under 10,000 characters.';
        }

        if (!empty($errors)) {
            return $this->twig->render($response, 'discussions/create.html.twig', [
                'group'  => $group,
                'errors' => $errors,
                'old'    => ['title' => $title, 'body' => $text],
            ]);
        }

        $this->db->prepare(
            'INSERT INTO group_discussion_topics (group_id, user_id, title, body) VALUES (?, ?, ?, ?)'
        )->execute([$id, $user['id'], $title, $text]);

        $this->flash->addMessage('success', 'Discussion started.');
        return $this->redirect($response, $request, '/groups/' . $id . '/discussions');
    }
}
