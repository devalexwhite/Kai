<?php
declare(strict_types=1);

// This endpoint only serves HTMX fragment requests.
if (!is_htmx()) {
    redirect('/?page=groups');
}

$pdo = get_db();

// ── SELECT: city chosen, return full widget in selected state ────────────────
if (isset($_GET['select'])) {
    $cityId = (int) $_GET['select'];
    $stmt   = $pdo->prepare('SELECT id, name, state FROM cities WHERE id = ?');
    $stmt->execute([$cityId]);
    $city = $stmt->fetch();

    if ($city) {
        ?>
        <div id="city-widget" class="city-widget city-widget--has-value">
            <input type="hidden" name="city_id" value="<?= (int) $city['id'] ?>">
            <div class="city-widget__chosen">
                <span><?= e($city['name']) ?>, <?= e($city['state']) ?></span>
                <button type="button" class="btn btn--ghost btn--sm"
                        hx-get="/?page=city_search&reset=1"
                        hx-target="#city-widget"
                        hx-swap="outerHTML">
                    Change
                </button>
            </div>
        </div>
        <?php
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
                    hx-get="/?page=city_search&select=<?= (int) $city['id'] ?>"
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
        hx-get="/?page=city_search"
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
