<?php
declare(strict_types=1);

namespace App\Action\Groups;

use App\Support\RedirectTrait;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class GroupCreateSubmitAction
{
    use RedirectTrait;

    public function __construct(
        private Twig $twig,
        private PDO $db,
        private Messages $flash,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $user = $request->getAttribute("user");
        $body = (array) $request->getParsedBody();
        $name = trim($body["name"] ?? "");
        $desc = trim($body["description"] ?? "");
        $cityId = (int) ($body["city_id"] ?? 0);
        $errors = [];

        if (mb_strlen($name) < 2) {
            $errors["name"] = "Group name must be at least 2 characters.";
        } elseif (mb_strlen($name) > 100) {
            $errors["name"] = "Group name must be under 100 characters.";
        }

        if (mb_strlen($desc) > 2000) {
            $errors["description"] =
                "Description must be under 2000 characters.";
        }

        if ($cityId === 0) {
            $errors["city_id"] = "Please select a city.";
        } else {
            $check = $this->db->prepare("SELECT id FROM cities WHERE id = ?");
            $check->execute([$cityId]);
            if (!$check->fetch()) {
                $errors["city_id"] = "Please select a valid city.";
            }
        }

        if (empty($errors)) {
            $slug = $this->generateUniqueSlug($name);

            $this->db
                ->prepare(
                    "INSERT INTO user_groups (name, slug, description, city_id, creator_id) VALUES (?, ?, ?, ?, ?)",
                )
                ->execute([$name, $slug, $desc, $cityId, $user["id"]]);

            $groupId = (int) $this->db->lastInsertId();

            $this->db
                ->prepare(
                    "INSERT IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)",
                )
                ->execute([$groupId, $user["id"]]);

            $this->flash->addMessage("success", "Your group has been created!");
            return $this->redirect($response, $request, "/groups/" . $slug);
        }

        $preselected = null;
        if ($cityId > 0) {
            $stmt = $this->db->prepare(
                "SELECT id, name, state FROM cities WHERE id = ?",
            );
            $stmt->execute([$cityId]);
            $preselected = $stmt->fetch() ?: null;
        }

        $cities = $this->db
            ->query("SELECT id, name, state FROM cities ORDER BY state, name")
            ->fetchAll();

        return $this->twig->render($response, "groups/create.html.twig", [
            "errors" => $errors,
            "old" => [
                "name" => $name,
                "description" => $desc,
                "city_id" => (string) $cityId,
            ],
            "preselected" => $preselected,
            "cities" => $cities,
        ]);
    }

    private const RESERVED_SLUGS = [
        "create",
        "delete",
        "update",
        "new",
        "patch",
        "edit",
        "discussions",
        "events",
    ];

    private function generateUniqueSlug(string $name): string
    {
        $base = strtolower($name);
        $base = (string) preg_replace("/[^a-z0-9]+/", "-", $base);
        $base = trim($base, "-");

        if ($base === "") {
            $base = "group";
        }

        $slug = $base;
        $i = 2;

        while (true) {
            $reserved = in_array($slug, self::RESERVED_SLUGS, true);
            $check = $this->db->prepare(
                "SELECT id FROM user_groups WHERE slug = ?",
            );
            $check->execute([$slug]);
            if (!$reserved && !$check->fetch()) {
                break;
            }
            $slug = $base . "-" . $i++;
        }

        return $slug;
    }
}
