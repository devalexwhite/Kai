<?php
declare(strict_types=1);

$pdo  = get_db();
$user = current_user();

// Determine which city to show in the explore section
$cityId = null;
if ($user) {
    $cityId = (int) $user['city_id'];
} elseif (isset($_GET['city_id'])) {
    $submitted = (int) $_GET['city_id'];
    if ($submitted > 0) {
        $check = $pdo->prepare('SELECT id FROM cities WHERE id = ?');
        $check->execute([$submitted]);
        if ($check->fetch()) {
            $cityId = $submitted;
        }
    }
}

$exploreCity = null;
if ($cityId !== null) {
    $stmt = $pdo->prepare('SELECT id, name, state FROM cities WHERE id = ?');
    $stmt->execute([$cityId]);
    $exploreCity = $stmt->fetch() ?: null;
}

// Fall back to the city with the most groups
if ($exploreCity === null) {
    $exploreCity = $pdo->query("
        SELECT c.id, c.name, c.state
        FROM cities c
        JOIN user_groups g ON g.city_id = c.id
        GROUP BY c.id
        ORDER BY COUNT(g.id) DESC
        LIMIT 1
    ")->fetch() ?: null;
}

// Query up to 6 groups for the explore city
$groups = [];
if ($exploreCity !== null) {
    $userId = $user ? (int) $user['id'] : 0;
    $stmt   = $pdo->prepare("
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
    $stmt->execute([$userId, (int) $exploreCity['id']]);
    $groups = $stmt->fetchAll();
}

ob_start();
?>
<section class="hero">
    <div class="container">
        <h1 class="hero__headline">Find your people.<br>Build your community.</h1>
        <p class="hero__sub">Kai brings people together through local events, shared interests, and the groups that make life richer.</p>
        <div class="hero__actions">
            <?php if (current_user()): ?>
                <a href="/?page=dashboard" class="btn btn--primary btn--lg">Go to Dashboard</a>
            <?php else: ?>
                <a href="/?page=signup" class="btn btn--primary btn--lg">Get started — it&rsquo;s free</a>
                <a href="/?page=signin" class="btn btn--ghost btn--lg">Sign in</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($exploreCity !== null): ?>
<section class="explore-section">
    <div class="container">
        <div class="explore-section__header">
            <h2>Explore groups in your city</h2>
            <div id="city-widget" class="city-widget city-widget--has-value">
                <div class="city-widget__chosen">
                    <span><?= e($exploreCity['name']) ?>, <?= e($exploreCity['state']) ?></span>
                    <button type="button" class="btn btn--ghost btn--sm"
                            hx-get="/?page=city_search&reset=1&mode=explore"
                            hx-target="#city-widget"
                            hx-swap="outerHTML">
                        Change
                    </button>
                </div>
            </div>
        </div>

        <?php $oob = false; include __DIR__ . '/../templates/explore_groups_grid.php'; ?>

        <div class="explore-section__footer">
            <a href="/?page=groups" class="btn btn--ghost btn--sm">Browse all groups</a>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="how-it-works">
    <div class="container">
        <h2 class="section-title">How Kai works</h2>
        <ol class="steps">
            <li class="step">
                <span class="step__number">1</span>
                <h3>Create an account</h3>
                <p>Sign up in seconds. No credit card, no noise &mdash; just you and your community.</p>
            </li>
            <li class="step">
                <span class="step__number">2</span>
                <h3>Join groups</h3>
                <p>Discover groups organised around your interests, neighbourhood, or profession.</p>
            </li>
            <li class="step">
                <span class="step__number">3</span>
                <h3>Attend events</h3>
                <p>RSVP for meetups, workshops, and gatherings happening near you every week.</p>
            </li>
        </ol>
    </div>
</section>

<section class="features">
    <div class="container">
        <h2 class="section-title">Everything you need to connect</h2>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-card__icon" aria-hidden="true">&#128101;</div>
                <h3>Groups</h3>
                <p>Join or create groups around any topic. Hiking, coding, photography, cooking &mdash; find your niche.</p>
            </div>
            <div class="feature-card">
                <div class="feature-card__icon" aria-hidden="true">&#128197;</div>
                <h3>Events</h3>
                <p>Browse upcoming events in your area. Online and in-person. One-offs and recurring.</p>
            </div>
            <div class="feature-card">
                <div class="feature-card__icon" aria-hidden="true">&#127758;</div>
                <h3>Local focus</h3>
                <p>Kai puts your city first. Real connections start with real proximity.</p>
            </div>
        </div>
    </div>
</section>

<section class="cta-band">
    <div class="container">
        <h2>Ready to get started?</h2>
        <p>Join thousands of people building real connections through Kai.</p>
        <?php if (!current_user()): ?>
            <a href="/?page=signup" class="btn btn--primary btn--lg">Create your free account</a>
        <?php else: ?>
            <a href="/?page=dashboard" class="btn btn--primary btn--lg">Go to Dashboard</a>
        <?php endif; ?>
    </div>
</section>
<?php
render('Kai — Find your people');
