<?php
declare(strict_types=1);

// This endpoint only serves HTMX fragment requests.
if (!is_htmx()) {
    redirect('/?page=groups');
}

$pdo         = get_db();
$user        = current_user();
$exploreMode = ($_GET['mode'] ?? '') === 'explore';
$modeParam   = $exploreMode ? '&mode=explore' : '';

// ── SELECT: city chosen, return full widget in selected state ────────────────
if (isset($_GET['select'])) {
    $cityId = (int) $_GET['select'];
    if ($cityId > 0) {
        $stmt = $pdo->prepare('SELECT id, name, state FROM cities WHERE id = ?');
        $stmt->execute([$cityId]);
        $city = $stmt->fetch();
    } else {
        $city = false;
    }

    if ($city) {
        ?>
        <div id="city-widget" class="city-widget city-widget--has-value">
            <input type="hidden" name="city_id" value="<?= (int) $city['id'] ?>">
            <div class="city-widget__chosen">
                <span><?= e($city['name']) ?>, <?= e($city['state']) ?></span>
                <button type="button" class="btn btn--ghost btn--sm"
                        hx-get="/?page=city_search&reset=1<?= $modeParam ?>"
                        hx-target="#city-widget"
                        hx-swap="outerHTML">
                    Change
                </button>
            </div>
        </div>
        <?php
        if ($exploreMode) {
            $userId = $user ? (int) $user['id'] : 0;
            $groupsStmt = $pdo->prepare("
                SELECT g.id, g.name,
                       COUNT(DISTINCT m.id) AS member_count,
                       MIN(e.event_date) AS next_event_date,
                       EXISTS (SELECT 1 FROM group_members WHERE group_id = g.id AND user_id = ?) AS is_member
                FROM user_groups g
                LEFT JOIN group_members m ON m.group_id = g.id
                LEFT JOIN group_events e ON e.group_id = g.id AND e.event_date >= date('now')
                WHERE g.city_id = ?
                GROUP BY g.id
                ORDER BY
                    CASE WHEN MIN(e.event_date) IS NULL THEN 1 ELSE 0 END,
                    MIN(e.event_date) ASC,
                    COUNT(DISTINCT m.id) DESC
                LIMIT 6
            ");
            $groupsStmt->execute([$userId, (int) $city['id']]);
            $groups = $groupsStmt->fetchAll();
            $oob = true;
            include __DIR__ . '/../templates/explore_groups_grid.php';
        }
        exit;
    }
    // Unknown ID — fall through to reset/search state
}

// ── SEARCH: return results fragment that replaces #city-results ──────────────
if (isset($_GET['city_q'])) {
    $raw = trim($_GET['city_q'] ?? '');

    if (mb_strlen($raw) === 0) {
        echo '<div id="city-results" class="city-widget__results"></div>';
        exit;
    }

    // Escape LIKE special characters in user input
    $escaped       = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $raw);
    $likePattern   = '%' . $escaped . '%';
    $prefixPattern = $escaped . '%';

    $stmt = $pdo->prepare("
        SELECT id, name, state FROM cities
        WHERE name LIKE ? ESCAPE '\\' OR state LIKE ? ESCAPE '\\'
        ORDER BY
            CASE WHEN name LIKE ? ESCAPE '\\' THEN 0 ELSE 1 END,
            state, name
        LIMIT 10
    ");
    $stmt->execute([$likePattern, $likePattern, $prefixPattern]);
    $results = $stmt->fetchAll();

    if (empty($results)) {
        ?>
        <div id="city-results" class="city-widget__results city-widget__results--empty">
            No cities match "<?= e($raw) ?>"
        </div>
        <?php
        exit;
    }
    ?>
    <div id="city-results" class="city-widget__results" role="listbox">
        <?php foreach ($results as $city): ?>
            <button type="button" class="city-widget__option" role="option"
                    hx-get="/?page=city_search&select=<?= (int) $city['id'] ?><?= $modeParam ?>"
                    hx-target="#city-widget"
                    hx-swap="outerHTML">
                <?= e($city['name']) ?>, <?= e($city['state']) ?>
            </button>
        <?php endforeach; ?>
    </div>
    <?php
    exit;
}

// ── RESET: return full widget in empty search state ──────────────────────────
?>
<div id="city-widget" class="city-widget">
    <input
        type="text"
        class="city-widget__input"
        name="city_q"
        placeholder="Search cities…"
        autocomplete="off"
        autofocus
        hx-get="/?page=city_search<?= $modeParam ?>"
        hx-trigger="input changed delay:300ms"
        hx-target="#city-results"
        hx-swap="outerHTML"
        aria-label="Search for a city"
        aria-autocomplete="list"
        aria-controls="city-results"
    >
    <input type="hidden" name="city_id" value="">
    <div id="city-results" class="city-widget__results" role="listbox"></div>
</div>
