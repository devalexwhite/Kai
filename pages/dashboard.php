<?php
declare(strict_types=1);

require_auth();

$user = current_user();
$pdo  = get_db();

// Groups the user has joined
$joinedStmt = $pdo->prepare("
    SELECT g.id, g.name, c.name AS city_name, c.state AS city_state,
           COUNT(m2.id) AS member_count
    FROM group_members m
    JOIN user_groups g  ON g.id = m.group_id
    JOIN cities c       ON c.id = g.city_id
    LEFT JOIN group_members m2 ON m2.group_id = g.id
    WHERE m.user_id = ?
    GROUP BY g.id
    ORDER BY m.joined_at DESC
");
$joinedStmt->execute([$user['id']]);
$joinedGroups = $joinedStmt->fetchAll();

// Groups the user has not joined, ordered by popularity
$suggestedStmt = $pdo->prepare("
    SELECT g.id, g.name, c.name AS city_name, c.state AS city_state,
           COUNT(m.id) AS member_count
    FROM user_groups g
    JOIN cities c ON c.id = g.city_id
    LEFT JOIN group_members m ON m.group_id = g.id
    WHERE g.id NOT IN (
        SELECT group_id FROM group_members WHERE user_id = ?
    ) AND
    c.id = ?
    GROUP BY g.id
    ORDER BY member_count DESC
    LIMIT 3
");
$suggestedStmt->execute([$user['id'], $user['city_id']]);
$suggestedGroups = $suggestedStmt->fetchAll();

ob_start();
?>
<section class="dashboard">
    <div class="container">
        <div class="dashboard__header">
            <div>
                <h1 class="dashboard__greeting">Welcome back, <?= e($user['name']) ?>!</h1>
                <p class="dashboard__date"><?= date('l, F j, Y') ?></p>
            </div>
        </div>

        <div class="dashboard__stats">
            <div class="stat-card">
                <span class="stat-card__value">0</span>
                <span class="stat-card__label">Upcoming events</span>
            </div>
            <div class="stat-card">
                <span class="stat-card__value"><?= count($joinedGroups) ?></span>
                <span class="stat-card__label">Groups joined</span>
            </div>
            <div class="stat-card">
                <span class="stat-card__value">0</span>
                <span class="stat-card__label">Events attended</span>
            </div>
        </div>
    </div>

    <div class="container">
        <section class="dashboard-section">
            <div class="dashboard-section__header">
                <h2>Your upcoming events</h2>
                <a href="#" class="btn btn--ghost btn--sm">Browse events</a>
            </div>
            <div class="placeholder-list">
                <div class="placeholder-card placeholder-card--empty">
                    <p>You haven&rsquo;t RSVP&rsquo;d to any events yet.</p>
                    <a href="#" class="btn btn--primary btn--sm">Find events near you</a>
                </div>
            </div>
        </section>

        <section class="dashboard-section">
            <div class="dashboard-section__header">
                <h2>Your groups</h2>
                <a href="/?page=groups" class="btn btn--ghost btn--sm">Explore groups</a>
            </div>
            <?php if (empty($joinedGroups)): ?>
                <div class="group-grid">
                    <div class="placeholder-card placeholder-card--empty">
                        <p>You haven&rsquo;t joined any groups yet.</p>
                        <a href="/?page=groups" class="btn btn--primary btn--sm">Find groups</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="group-grid">
                    <?php foreach ($joinedGroups as $group): ?>
                        <?php $color = group_color((int) $group['id']); ?>
                        <article class="group-card">
                            <div class="group-card__cover" style="background: <?= $color ?>;" aria-hidden="true"></div>
                            <div class="group-card__body">
                                <h3><?= e($group['name']) ?></h3>
                                <p>
                                    <?= e($group['city_name']) ?>, <?= e($group['city_state']) ?>
                                    &middot;
                                    <?= (int) $group['member_count'] ?> <?= (int) $group['member_count'] === 1 ? 'member' : 'members' ?>
                                </p>
                                <a href="/?page=group_view&id=<?= (int) $group['id'] ?>" class="btn btn--ghost btn--sm">View group</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!empty($suggestedGroups)): ?>
        <section class="dashboard-section">
            <div class="dashboard-section__header">
                <h2>Suggested for you</h2>
                <a href="/?page=groups" class="btn btn--ghost btn--sm">Browse all</a>
            </div>
            <div class="group-grid">
                <?php foreach ($suggestedGroups as $group): ?>
                    <?php $color = group_color((int) $group['id']); ?>
                    <article class="group-card">
                        <div class="group-card__cover" style="background: <?= $color ?>;" aria-hidden="true"></div>
                        <div class="group-card__body">
                            <h3><?= e($group['name']) ?></h3>
                            <p>
                                <?= e($group['city_name']) ?>, <?= e($group['city_state']) ?>
                                &middot;
                                <?= (int) $group['member_count'] ?> <?= (int) $group['member_count'] === 1 ? 'member' : 'members' ?>
                            </p>
                            <a href="/?page=group_view&id=<?= (int) $group['id'] ?>" class="btn btn--ghost btn--sm">View group</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</section>
<?php
render('Dashboard — Kai');
