<?php
declare(strict_types=1);

$pdo = get_db();

$stmt = $pdo->query("
    SELECT g.id, g.name, g.description,
           c.name AS city_name, c.state AS city_state,
           COUNT(m.id) AS member_count
    FROM user_groups g
    JOIN cities c ON c.id = g.city_id
    LEFT JOIN group_members m ON m.group_id = g.id
    GROUP BY g.id
    ORDER BY member_count DESC, g.created_at DESC
");
$groups = $stmt->fetchAll();

ob_start();
?>
<section class="groups-page">
    <div class="container">
        <div class="groups-page__header">
            <h1>Groups</h1>
            <?php if (current_user()): ?>
                <a href="/?page=group_create" class="btn btn--primary">Start a group</a>
            <?php else: ?>
                <a href="/?page=signin" class="btn btn--primary">Sign in to start a group</a>
            <?php endif; ?>
        </div>

        <?php if (empty($groups)): ?>
            <div class="placeholder-card placeholder-card--empty">
                <p>No groups yet. Be the first to start one!</p>
                <?php if (current_user()): ?>
                    <a href="/?page=group_create" class="btn btn--primary btn--sm">Start a group</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="group-grid">
                <?php foreach ($groups as $group): ?>
                    <article class="group-card">
                        <div class="group-card__cover" style="background: <?= group_color((int) $group['id']) ?>;" aria-hidden="true"></div>
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
    </div>
</section>
<?php
render('Groups — Kai');
