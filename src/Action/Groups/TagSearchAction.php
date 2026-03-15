<?php
declare(strict_types=1);

namespace App\Action\Groups;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class TagSearchAction
{
    use RedirectTrait;

    public function __construct(
        private readonly Twig $twig,
        private readonly PDO  $db,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        if ($request->getHeaderLine('HX-Request') !== 'true') {
            return $this->redirect($response, $request, '/groups');
        }

        $params    = $request->getQueryParams();
        $groupSlug = $params['group_slug'] ?? '';
        $raw       = trim($params['name'] ?? '');

        if (mb_strlen($raw) === 0) {
            $response->getBody()->write('<div id="tag-results" class="tag-widget__results"></div>');
            return $response;
        }

        $escaped       = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $raw);
        $likePattern   = '%' . $escaped . '%';
        $prefixPattern = $escaped . '%';

        $stmt = $this->db->prepare("
            SELECT id, name FROM tags
            WHERE name LIKE ? ESCAPE '!'
            ORDER BY
                CASE WHEN name LIKE ? ESCAPE '!' THEN 0 ELSE 1 END,
                name
            LIMIT 8
        ");
        $stmt->execute([$likePattern, $prefixPattern]);
        $results = $stmt->fetchAll();

        return $this->twig->render($response, 'partials/tag_search_results.html.twig', [
            'results'    => $results,
            'group_slug' => $groupSlug,
        ]);
    }
}
