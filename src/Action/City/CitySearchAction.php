<?php
declare(strict_types=1);

namespace App\Action\City;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class CitySearchAction
{
    use RedirectTrait;

    public function __construct(
        private Twig $twig,
        private PDO  $db,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        if ($request->getHeaderLine('HX-Request') !== 'true') {
            return $this->redirect($response, $request, '/groups');
        }

        $params      = $request->getQueryParams();
        $user        = $request->getAttribute('user');
        $exploreMode = ($params['mode'] ?? '') === 'explore';
        $modeParam   = $exploreMode ? '&mode=explore' : '';

        // ── SELECT: city chosen ───────────────────────────────────────────────
        if (isset($params['select'])) {
            $cityId = (int) $params['select'];
            $city   = false;

            if ($cityId > 0) {
                $stmt = $this->db->prepare('SELECT id, name, state FROM cities WHERE id = ?');
                $stmt->execute([$cityId]);
                $city = $stmt->fetch();
            }

            if ($city) {
                $body = $this->twig->fetch('partials/city_chosen.html.twig', [
                    'city'       => $city,
                    'modeParam'  => $modeParam,
                    'exploreMode' => $exploreMode,
                ]);

                if ($exploreMode) {
                    $userId = $user ? (int) $user['id'] : 0;
                    $groupsStmt = $this->db->prepare("
                        SELECT g.id, g.slug, g.name,
                               COUNT(DISTINCT m.id) AS member_count,
                               MIN(e.event_date) AS next_event_date,
                               EXISTS (SELECT 1 FROM group_members WHERE group_id = g.id AND user_id = ?) AS is_member
                        FROM user_groups g
                        LEFT JOIN group_members m ON m.group_id = g.id
                        LEFT JOIN group_events e ON e.group_id = g.id AND e.event_date >= CURDATE()
                        WHERE g.city_id = ?
                        GROUP BY g.id
                        ORDER BY
                            CASE WHEN MIN(e.event_date) IS NULL THEN 1 ELSE 0 END,
                            MIN(e.event_date) ASC,
                            COUNT(DISTINCT m.id) DESC
                        LIMIT 6
                    ");
                    $groupsStmt->execute([$userId, (int) $city['id']]);

                    $body .= $this->twig->fetch('partials/explore_groups_grid.html.twig', [
                        'groups' => $groupsStmt->fetchAll(),
                        'oob'    => true,
                    ]);
                }

                $response->getBody()->write($body);
                return $response;
            }
            // Unknown city ID — fall through to reset/search state
        }

        // ── SEARCH: return matching city buttons ──────────────────────────────
        if (isset($params['city_q'])) {
            $raw = trim($params['city_q'] ?? '');

            if (mb_strlen($raw) === 0) {
                $response->getBody()->write('<div id="city-results" class="city-widget__results"></div>');
                return $response;
            }

            $escaped       = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $raw);
            $likePattern   = '%' . $escaped . '%';
            $prefixPattern = $escaped . '%';

            $stmt = $this->db->prepare("
                SELECT id, name, state FROM cities
                WHERE name LIKE ? ESCAPE '!' OR state LIKE ? ESCAPE '!'
                ORDER BY
                    CASE WHEN name LIKE ? ESCAPE '!' THEN 0 ELSE 1 END,
                    state, name
                LIMIT 10
            ");
            $stmt->execute([$likePattern, $likePattern, $prefixPattern]);
            $results = $stmt->fetchAll();

            return $this->twig->render($response, 'partials/city_search_results.html.twig', [
                'results'    => $results,
                'raw'        => $raw,
                'modeParam'  => $modeParam,
                'exploreMode' => $exploreMode,
            ]);
        }

        // ── RESET: return empty search widget ─────────────────────────────────
        return $this->twig->render($response, 'partials/city_widget_search.html.twig', [
            'modeParam' => $modeParam,
        ]);
    }
}
