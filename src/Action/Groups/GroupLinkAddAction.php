<?php
declare(strict_types=1);

namespace App\Action\Groups;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class GroupLinkAddAction
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

        $stmt = $this->db->prepare('SELECT id, slug, name, creator_id FROM user_groups WHERE slug = ?');
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
            $this->flash->addMessage('error', 'You do not have permission to add links to this group.');
            return $this->redirect($response, $request, '/groups/' . $slug . '/edit');
        }

        $id     = (int) $group['id'];
        $body   = (array) $request->getParsedBody();
        $title  = trim($body['title'] ?? '');
        $url    = trim($body['url'] ?? '');
        $errors = [];

        if ($title === '') {
            $errors['title'] = 'Link title is required.';
        } elseif (mb_strlen($title) > 100) {
            $errors['title'] = 'Link title must be under 100 characters.';
        }

        if ($url === '') {
            $errors['url'] = 'URL is required.';
        } elseif (mb_strlen($url) > 2048) {
            $errors['url'] = 'URL must be under 2048 characters.';
        } else {
            $scheme = is_string(parse_url($url, PHP_URL_SCHEME)) ? parse_url($url, PHP_URL_SCHEME) : '';
            if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
                $errors['url'] = 'Please enter a valid URL (must start with http:// or https://).';
            }
        }

        $isHtmx = $request->getHeaderLine('HX-Request') === 'true';

        if (!empty($errors)) {
            if ($isHtmx) {
                $linksStmt = $this->db->prepare(
                    'SELECT id, title, url FROM group_links WHERE group_id = ? ORDER BY created_at ASC'
                );
                $linksStmt->execute([$id]);
                return $this->twig->render($response, 'partials/group_links_edit.html.twig', [
                    'group_slug' => $slug,
                    'links'      => $linksStmt->fetchAll(),
                    'errors'     => $errors,
                    'old'        => ['title' => $title, 'url' => $url],
                ]);
            }
            $this->flash->addMessage('error', implode(' ', $errors));
            return $this->redirect($response, $request, '/groups/' . $slug . '/edit');
        }

        $this->db->prepare(
            'INSERT INTO group_links (group_id, title, url) VALUES (?, ?, ?)'
        )->execute([$id, $title, $url]);

        if ($isHtmx) {
            $linksStmt = $this->db->prepare(
                'SELECT id, title, url FROM group_links WHERE group_id = ? ORDER BY created_at ASC'
            );
            $linksStmt->execute([$id]);
            return $this->twig->render($response, 'partials/group_links_edit.html.twig', [
                'group_slug' => $slug,
                'links'      => $linksStmt->fetchAll(),
                'errors'     => [],
                'old'        => [],
            ]);
        }

        $this->flash->addMessage('success', 'Link added.');
        return $this->redirect($response, $request, '/groups/' . $slug . '/edit');
    }
}
