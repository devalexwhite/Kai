<?php

declare(strict_types=1);

namespace App\Action\Groups;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class GroupLegacyRedirectAction
{
    public function __construct(private PDO $db) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id   = (int) $args['id'];
        $stmt = $this->db->prepare('SELECT slug FROM user_groups WHERE id = ?');
        $stmt->execute([$id]);
        $group = $stmt->fetch();

        if (!$group) {
            return $response->withStatus(404);
        }

        return $response
            ->withHeader('Location', '/groups/' . $group['slug'])
            ->withStatus(301);
    }
}
