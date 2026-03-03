<?php
declare(strict_types=1);

$user = current_user();
$pdo  = get_db();

$myRsvpdEvents   = [];
$myGroupEvents   = [];
$cityEvents      = [];

if ($user !== null) {
    // Section 1: All upcoming events the user has RSVPd to
    $stmt = $pdo->prepare("
        SELECT e.id, e.title, e.event_date, e.event_time, e.location, e.meeting_url,
               g.id AS group_id, g.name AS group_name
        FROM event_rsvps r
        JOIN group_events e ON e.id = r.event_id
        JOIN user_groups g  ON g.id = e.group_id
        WHERE r.user_id = ?
          AND (e.event_date > date('now') OR (e.event_date = date('now') AND e.event_time >= time('now')))
        ORDER BY e.event_date ASC, e.event_time ASC
    ");
    $stmt->execute([$user['id']]);
    $myRsvpdEvents = $stmt->fetchAll();

    // Section 2: Events from groups I belong to (member or creator) that I haven't RSVPd to, next 3 months
    $stmt = $pdo->prepare("
        SELECT e.id, e.title, e.event_date, e.event_time, e.location, e.meeting_url,
               g.id AS group_id, g.name AS group_name
        FROM group_events e
        JOIN user_groups g ON g.id = e.group_id
        WHERE (
            EXISTS (SELECT 1 FROM group_members gm WHERE gm.group_id = e.group_id AND gm.user_id = ?)
            OR g.creator_id = ?
        )
          AND NOT EXISTS (SELECT 1 FROM event_rsvps r WHERE r.event_id = e.id AND r.user_id = ?)
          AND (e.event_date > date('now') OR (e.event_date = date('now') AND e.event_time >= time('now')))
          AND e.event_date <= date('now', '+3 months')
        ORDER BY e.event_date ASC, e.event_time ASC
    ");
    $stmt->execute([$user['id'], $user['id'], $user['id']]);
    $myGroupEvents = $stmt->fetchAll();

    // Section 3: Events in my city from groups I'm not a member of and didn't create, next 3 months
    // Also excludes events the user has already RSVPd to (those appear in Section 1)
    $stmt = $pdo->prepare("
        SELECT e.id, e.title, e.event_date, e.event_time, e.location, e.meeting_url,
               g.id AS group_id, g.name AS group_name
        FROM group_events e
        JOIN user_groups g ON g.id = e.group_id
        WHERE g.city_id = ?
          AND g.creator_id != ?
          AND NOT EXISTS (SELECT 1 FROM group_members gm WHERE gm.group_id = g.id AND gm.user_id = ?)
          AND NOT EXISTS (SELECT 1 FROM event_rsvps r WHERE r.event_id = e.id AND r.user_id = ?)
          AND (e.event_date > date('now') OR (e.event_date = date('now') AND e.event_time >= time('now')))
          AND e.event_date <= date('now', '+3 months')
        ORDER BY e.event_date ASC, e.event_time ASC
    ");
    $stmt->execute([$user['city_id'], $user['id'], $user['id'], $user['id']]);
    $cityEvents = $stmt->fetchAll();
}

ob_start();
?>
<section class="browse-events">
    <div class="container">
        <div class="browse-events__header">
            <h1>Browse events</h1>
        </div>

        <?php if ($user === null): ?>
            <div class="placeholder-card placeholder-card--empty">
                <p>Sign in to browse events near you.</p>
                <a href="/?page=signin" class="btn btn--primary btn--sm">Sign in</a>
            </div>
        <?php else: ?>

            <?php if (!empty($myRsvpdEvents)): ?>
            <section class="dashboard-section">
                <div class="dashboard-section__header">
                    <h2>Your upcoming events</h2>
                </div>
                <div class="event-list">
                    <?php foreach ($myRsvpdEvents as $ev): ?>
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
                                    <a href="/?page=group_view&id=<?= (int) $ev['group_id'] ?>"><?= e($ev['group_name']) ?></a>
                                    <?php if ($ev['location'] !== ''): ?>
                                        &middot; <?= e($ev['location']) ?>
                                    <?php endif; ?>
                                    <?php if ($ev['meeting_url'] !== ''): ?>
                                        &middot; Online
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="event-card__countdown"><?= e($countdown) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!empty($myGroupEvents)): ?>
            <section class="dashboard-section">
                <div class="dashboard-section__header">
                    <h2>From your groups</h2>
                </div>
                <div class="event-list">
                    <?php foreach ($myGroupEvents as $ev): ?>
                        <?php
                        $dt   = new DateTimeImmutable($ev['event_date'] . ' ' . $ev['event_time']);
                        $date = $dt->format('M j, Y');
                        $time = $dt->format('g:i A');
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
                                    <a href="/?page=group_view&id=<?= (int) $ev['group_id'] ?>"><?= e($ev['group_name']) ?></a>
                                    <?php if ($ev['location'] !== ''): ?>
                                        &middot; <?= e($ev['location']) ?>
                                    <?php endif; ?>
                                    <?php if ($ev['meeting_url'] !== ''): ?>
                                        &middot; Online
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="event-card__countdown"><?= e($countdown) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!empty($cityEvents)): ?>
            <section class="dashboard-section">
                <div class="dashboard-section__header">
                    <h2>More events near you</h2>
                </div>
                <div class="event-list">
                    <?php foreach ($cityEvents as $ev): ?>
                        <?php
                        $dt   = new DateTimeImmutable($ev['event_date'] . ' ' . $ev['event_time']);
                        $date = $dt->format('M j, Y');
                        $time = $dt->format('g:i A');
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
                                    <a href="/?page=group_view&id=<?= (int) $ev['group_id'] ?>"><?= e($ev['group_name']) ?></a>
                                    <?php if ($ev['location'] !== ''): ?>
                                        &middot; <?= e($ev['location']) ?>
                                    <?php endif; ?>
                                    <?php if ($ev['meeting_url'] !== ''): ?>
                                        &middot; Online
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="event-card__countdown"><?= e($countdown) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if (empty($myRsvpdEvents) && empty($myGroupEvents) && empty($cityEvents)): ?>
                <div class="placeholder-card placeholder-card--empty">
                    <p>No upcoming events found. Join some groups to discover events near you.</p>
                    <a href="/?page=groups" class="btn btn--primary btn--sm">Browse groups</a>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</section>
<?php
render('Browse Events — Kai');
