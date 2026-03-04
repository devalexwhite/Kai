<?php
declare(strict_types=1);

require_auth();

$user = current_user();
$pdo  = get_db();

// Upcoming events the user has RSVPd to
$upcomingEventsStmt = $pdo->prepare("
    SELECT e.id, e.title, e.event_date, e.event_time, e.location, e.meeting_url,
           g.id AS group_id, g.name AS group_name
    FROM event_rsvps r
    JOIN group_events e  ON e.id = r.event_id
    JOIN user_groups g   ON g.id = e.group_id
    WHERE r.user_id = ?
      AND (e.event_date > date('now') OR (e.event_date = date('now') AND e.event_time >= time('now')))
    ORDER BY e.event_date ASC, e.event_time ASC
");
$upcomingEventsStmt->execute([$user['id']]);
$upcomingEvents = $upcomingEventsStmt->fetchAll();

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
                <span class="stat-card__value"><?= count($upcomingEvents) ?></span>
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
                <a href="/?page=browse_events" class="btn btn--ghost btn--sm">Browse events</a>
            </div>
            <?php if (empty($upcomingEvents)): ?>
                <div class="placeholder-list">
                    <div class="placeholder-card placeholder-card--empty">
                        <p>You haven&rsquo;t RSVP&rsquo;d to any events yet.</p>
                        <a href="/?page=groups" class="btn btn--primary btn--sm">Find events near you</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="event-list">
                    <?php foreach ($upcomingEvents as $ev): ?>
                        <?php
                        $dt        = new DateTimeImmutable($ev['event_date'] . ' ' . $ev['event_time']);
                        $date      = $dt->format('M j, Y');
                        $time      = $dt->format('g:i A');
                        $countdown = event_countdown($dt);
                        ?>
                        <article class="event-card">
                            <div class="event-card__datetime">
                                <span class="event-card__date"><?= e($date) ?></span>
                                <span class="event-card__time"><?= e($time) ?></span>
                            </div>
                            <div class="event-card__body">
                                <h3 class="event-card__title">
                                    <a href="/?page=event_view&id=<?= (int) $ev['id'] ?>"><?= e($ev['title']) ?></a>
                                </h3>
                                <p class="event-card__meta">
                                    <?= e($ev['group_name']) ?>
                                    <?php if ($ev['location'] !== ''): ?>
                                        &middot; <?= e($ev['location']) ?>
                                    <?php endif; ?>
                                    <?php if ($ev['meeting_url'] !== ''): ?>
                                        &middot; Online
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="event-card__actions">
                                <a href="/?page=event_view&id=<?= (int) $ev['id'] ?>">
                                    View 
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                      <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                                <span class="event-card__countdown"><?= e($countdown) ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="dashboard-section">
            <div class="dashboard-section__header">
                <h2>Your groups</h2>
                <a href="/?page=groups" class="btn btn--ghost btn--sm">Explore groups</a>
            </div>
            <?php if (empty($joinedGroups)): ?>                
                <div class="placeholder-card placeholder-card--empty">
                    <p>You haven&rsquo;t joined any groups yet.</p>
                    <a href="/?page=groups" class="btn btn--primary btn--sm">Find groups</a>
                </div>
            <?php else: ?>
                <div class="group-grid">
                    <?php foreach ($joinedGroups as $group): ?>
                        <?php $bg = group_background((int) $group['id']); ?>
                        <article class="group-card">
                            <div class="group-card__cover" style="background-size: 40px; background-image: url('<?= $bg ?>');" aria-hidden="true"></div>
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
                    <?php $bg = group_background((int) $group['id']); ?>
                    <article class="group-card">
                        <div class="group-card__cover" style="background-size: 40px; background-image: url('<?= $bg ?>');" aria-hidden="true"></div>
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
