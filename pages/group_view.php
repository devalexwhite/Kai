<?php
declare(strict_types=1);

$pdo = get_db();
$id  = (int) ($_GET['id'] ?? 0);

if ($id === 0) {
    redirect('/?page=groups');
}

$stmt = $pdo->prepare("
    SELECT g.id, g.name, g.description, g.creator_id, g.created_at,
           c.name AS city_name, c.state AS city_state,
           u.name AS creator_name
    FROM user_groups g
    JOIN cities c ON c.id = g.city_id
    JOIN users u  ON u.id = g.creator_id
    WHERE g.id = ?
");
$stmt->execute([$id]);
$group = $stmt->fetch();

if (!$group) {
    http_response_code(404);
    ob_start();
    ?>
    <section class="error-page container">
        <h1>Group not found</h1>
        <p><a href="/?page=groups">Browse groups</a></p>
    </section>
    <?php
    render('Not Found — Kai');
}

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ?');
$countStmt->execute([$id]);
$memberCount = (int) $countStmt->fetchColumn();

$user      = current_user();
$isMember  = false;
$isCreator = false;

if ($user) {
    $memberStmt = $pdo->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
    $memberStmt->execute([$id, $user['id']]);
    $isMember  = (bool) $memberStmt->fetch();
    $isCreator = ((int) $group['creator_id'] === (int) $user['id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_auth();
    csrf_verify();
    $authedUser = current_user(); // guaranteed non-null after require_auth()

    $action = $_POST['membership_action'] ?? '';

    if ($action === 'join' && !$isMember) {
        $pdo->prepare('INSERT OR IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)')
            ->execute([$id, $authedUser['id']]);
        flash('success', 'You joined ' . e($group['name']) . '!');
    } elseif ($action === 'leave' && $isMember && !$isCreator) {
        $pdo->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?')
            ->execute([$id, $authedUser['id']]);
        flash('success', 'You left ' . e($group['name']) . '.');
    }

    redirect('/?page=group_view&id=' . $id);
}

// Events
$showPast = isset($_GET['show_past']);
$today    = date('Y-m-d');
$now      = date('H:i');

$eventsStmt = $pdo->prepare("
    SELECT e.id, e.title, e.event_date, e.event_time, e.location, e.meeting_url,
           COUNT(r.id) AS rsvp_count
    FROM group_events e
    LEFT JOIN event_rsvps r ON r.event_id = e.id
    WHERE e.group_id = ?
    GROUP BY e.id
    ORDER BY e.event_date ASC, e.event_time ASC
");
$eventsStmt->execute([$id]);
$allEvents = $eventsStmt->fetchAll();

$upcomingEvents = [];
$pastEvents     = [];

foreach ($allEvents as $ev) {
    if ($ev['event_date'] > $today || ($ev['event_date'] === $today && $ev['event_time'] >= $now)) {
        $upcomingEvents[] = $ev;
    } else {
        $pastEvents[] = $ev;
    }
}
$pastEvents = array_reverse($pastEvents); // most recent past first
$pastCount  = count($pastEvents);

$bg = group_background((int) $group['id']);

ob_start();
?>
<section class="group-detail">
    <div class="container">
        <div class="group-detail__card">
            <div class="group-detail__cover" style="background-size: 40px;background-image: url('<?= $bg ?>');" aria-hidden="true"></div>
            <div class="group-detail__body">
                <h1><?= e($group['name']) ?></h1>
                <div class="group-detail__meta">
                    <span><?= e($group['city_name']) ?>, <?= e($group['city_state']) ?></span>
                    <span>
                        <?= (int) $memberCount ?>
                        <?= (int) $memberCount === 1 ? 'member' : 'members' ?>
                    </span>
                    <span>Organized by <?= e($group['creator_name']) ?></span>
                </div>

                <?php if ($group['description'] !== ''): ?>
                    <p class="group-detail__description"><?= nl2br(e($group['description'])) ?></p>
                <?php endif; ?>

                <div class="group-detail__actions">
                    <?php if ($user): ?>
                        <?php if ($isMember && !$isCreator): ?>
                            <form method="post" action="/?page=group_view&id=<?= (int) $group['id'] ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="membership_action" value="leave">
                                <button type="submit" class="btn btn--ghost">Leave group</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!$isMember): ?>
                            <form method="post" action="/?page=group_view&id=<?= (int) $group['id'] ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="membership_action" value="join">
                                <button type="submit" class="btn btn--primary">Join group</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($isCreator): ?>
                            <a href="/?page=group_edit&id=<?= (int) $group['id'] ?>" class="btn btn--ghost">Edit group</a>
                            <form method="post" action="/?page=group_delete">
                                <?= csrf_field() ?>
                                <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
                                <button type="submit" class="btn btn--danger">Delete group</button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="/?page=signin" class="btn btn--primary">Sign in to join</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="group-events">
            <div class="group-events__header">
                <h2>Events</h2>
                <?php if ($isCreator): ?>
                    <a href="/?page=event_create&group_id=<?= (int) $id ?>" class="btn btn--primary btn--sm">Create event</a>
                <?php endif; ?>
            </div>

            <?php if (empty($upcomingEvents)): ?>
                <div class="placeholder-card placeholder-card--empty">
                    <p>No upcoming events.<?php if ($isCreator): ?> Create one to get started.<?php endif; ?></p>
                    <?php if ($isCreator): ?>
                        <a href="/?page=event_create&group_id=<?= (int) $id ?>" class="btn btn--primary btn--sm">Create event</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="event-list">
                    <?php foreach ($upcomingEvents as $ev): ?>
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
                                    <?= (int) $ev['rsvp_count'] ?> going
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

            <?php if ($pastCount > 0): ?>
                <div id="past-events-section" class="group-events__past">
                    <?php if ($showPast): ?>
                        <h3 class="group-events__past-heading">Past events</h3>
                        <div class="event-list">
                            <?php foreach ($pastEvents as $ev): ?>
                                <?php
                                $dt   = new DateTimeImmutable($ev['event_date'] . ' ' . $ev['event_time']);
                                $date = $dt->format('M j, Y');
                                $time = $dt->format('g:i A');
                                ?>
                                <article class="event-card event-card--past">
                                    <div class="event-card__datetime">
                                        <span class="event-card__date"><?= e($date) ?></span>
                                        <span class="event-card__time"><?= e($time) ?></span>
                                    </div>
                                    <div class="event-card__body">
                                        <h3 class="event-card__title">
                                            <a href="/?page=event_view&id=<?= (int) $ev['id'] ?>"><?= e($ev['title']) ?></a>
                                        </h3>
                                        <p class="event-card__meta">
                                            <?= (int) $ev['rsvp_count'] ?> <?= (int) $ev['rsvp_count'] === 1 ? 'attendee' : 'attendees' ?>
                                            <?php if ($ev['location'] !== ''): ?>
                                                &middot; <?= e($ev['location']) ?>
                                            <?php endif; ?>
                                            <?php if ($ev['meeting_url'] !== ''): ?>
                                                &middot; Online
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <a href="/?page=group_view&id=<?= (int) $id ?>&show_past=1"
                           class="btn btn--ghost btn--sm"
                           hx-get="/?page=past_events&group_id=<?= (int) $id ?>"
                           hx-target="#past-events-section"
                           hx-swap="outerHTML">
                            Show past events (<?= $pastCount ?>)
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php
render($group['name'] . ' — Kai');
