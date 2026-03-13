<?php
declare(strict_types=1);

namespace App\Action\Groups;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class GroupEditSubmitAction
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

        $id     = (int) $group['id'];
        $body   = (array) $request->getParsedBody();
        $name   = trim($body['name'] ?? '');
        $desc   = trim($body['description'] ?? '');
        $cityId = (int) ($body['city_id'] ?? 0);
        $errors = [];

        if (mb_strlen($name) < 2) {
            $errors['name'] = 'Group name must be at least 2 characters.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Group name must be under 100 characters.';
        }

        if (mb_strlen($desc) > 2000) {
            $errors['description'] = 'Description must be under 2000 characters.';
        }

        if ($cityId === 0) {
            $errors['city_id'] = 'Please select a city.';
        } else {
            $check = $this->db->prepare('SELECT id FROM cities WHERE id = ?');
            $check->execute([$cityId]);
            if (!$check->fetch()) {
                $errors['city_id'] = 'Please select a valid city.';
            }
        }

        if (empty($errors)) {
            $this->db->prepare(
                'UPDATE user_groups SET name = ?, description = ?, city_id = ? WHERE id = ?'
            )->execute([$name, $desc, $cityId, $id]);

            $this->flash->addMessage('success', 'Group updated.');
            return $this->redirect($response, $request, '/groups/' . $slug);
        }

        $preselected = null;
        if ($cityId > 0) {
            $cityStmt = $this->db->prepare('SELECT id, name, state FROM cities WHERE id = ?');
            $cityStmt->execute([$cityId]);
            $preselected = $cityStmt->fetch() ?: null;
        }

        $cities = $this->db->query('SELECT id, name, state FROM cities ORDER BY state, name')->fetchAll();

        return $this->twig->render($response, 'groups/edit.html.twig', [
            'group'       => $group,
            'errors'      => $errors,
            'old'         => ['name' => $name, 'description' => $desc, 'city_id' => (string) $cityId],
            'preselected' => $preselected,
            'cities'      => $cities,
        ]);
    }
}
