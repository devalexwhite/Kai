<?php
declare(strict_types=1);

$pdo = get_db();
$id  = (int) ($_GET['id'] ?? 0);

if ($id === 0) {
    redirect('/?page=groups');
}

$stmt = $pdo->prepare("
    SELECT e.id, e.group_id, e.creator_id, e.title, e.description,
           e.event_date, e.event_time, e.location, e.meeting_url,
           g.name AS group_name, g.creator_id AS group_creator_id
    FROM group_events e
    JOIN user_groups g ON g.id = e.group_id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    ob_start();
    ?>
    <section class="error-page container">
        <h1>Event not found</h1>
        <p><a href="/?page=groups">Browse groups</a></p>
    </section>
    <?php
    render('Not Found — Kai');
}

$rsvpCountStmt = $pdo->prepare('SELECT COUNT(*) FROM event_rsvps WHERE event_id = ?');
$rsvpCountStmt->execute([$id]);
$rsvpCount = (int) $rsvpCountStmt->fetchColumn();

$user         = current_user();
$isAttendee   = false;
$isMember     = false;
$isEventOwner = false;
$isGroupOwner = false;

if ($user) {
    $rsvpStmt = $pdo->prepare('SELECT 1 FROM event_rsvps WHERE event_id = ? AND user_id = ?');
    $rsvpStmt->execute([$id, $user['id']]);
    $isAttendee = (bool) $rsvpStmt->fetch();

    $memberStmt = $pdo->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
    $memberStmt->execute([$event['group_id'], $user['id']]);
    $isMember = (bool) $memberStmt->fetch();

    $isEventOwner = ((int) $event['creator_id'] === (int) $user['id']);
    $isGroupOwner = ((int) $event['group_creator_id'] === (int) $user['id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_auth();
    csrf_verify();
    $authedUser = current_user();

    $action = $_POST['rsvp_action'] ?? '';

    if ($action === 'rsvp' && !$isAttendee) {
        $pdo->prepare('INSERT OR IGNORE INTO event_rsvps (event_id, user_id) VALUES (?, ?)')
            ->execute([$id, $authedUser['id']]);
        flash('success', 'You\'re going to ' . $event['title'] . '!');
    } elseif ($action === 'cancel' && $isAttendee) {
        $pdo->prepare('DELETE FROM event_rsvps WHERE event_id = ? AND user_id = ?')
            ->execute([$id, $authedUser['id']]);
        flash('success', 'Your RSVP has been cancelled.');
    }

    redirect('/?page=event_view&id=' . $id);
}

// Attendees with group membership status
$attendeesStmt = $pdo->prepare("
    SELECT u.name,
           CASE WHEN gm.id IS NOT NULL THEN 1 ELSE 0 END AS is_member
    FROM event_rsvps er
    JOIN users u ON u.id = er.user_id
    LEFT JOIN group_members gm ON gm.group_id = ? AND gm.user_id = er.user_id
    WHERE er.event_id = ?
    ORDER BY er.created_at ASC
");
$attendeesStmt->execute([$event['group_id'], $id]);
$attendees = $attendeesStmt->fetchAll();

$today  = date('Y-m-d');
$now    = date('H:i');
$isPast = $event['event_date'] < $today ||
          ($event['event_date'] === $today && $event['event_time'] < $now);

$dateTime      = new DateTimeImmutable($event['event_date'] . ' ' . $event['event_time']);
$formattedDate = $dateTime->format('l, F j, Y');
$formattedTime = $dateTime->format('g:i A');

ob_start();
?>
<section class="event-detail">
    <div class="container">
        <p class="event-detail__back">
            <a href="/?page=group_view&id=<?= (int) $event['group_id'] ?>">← <?= e($event['group_name']) ?></a>
        </p>

        <div class="event-detail__layout">
            <div class="event-detail__main">
                <div class="event-detail__card">
                    <?php if ($isPast): ?>
                        <span class="event-badge event-badge--past">Past event</span>
                    <?php else: ?>
                        <span class="event-badge event-badge--upcoming">Upcoming</span>
                    <?php endif; ?>

                    <h1><?= e($event['title']) ?></h1>

                    <div class="event-detail__meta">
                        <div class="event-meta-item">
                            <span class="event-meta-item__label">When</span>
                            <span><?= e($formattedDate) ?> at <?= e($formattedTime) ?></span>
                        </div>
                        <?php if ($event['location'] !== ''): ?>
                            <div class="event-meta-item">
                                <span class="event-meta-item__label">Where</span>
                                <span><?= e($event['location']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($event['meeting_url'] !== ''): ?>
                            <div class="event-meta-item">
                                <span class="event-meta-item__label">Online</span>
                                <a href="<?= e($event['meeting_url']) ?>" rel="noopener noreferrer"><?= e($event['meeting_url']) ?></a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($event['description'] !== ''): ?>
                        <div class="event-detail__description">
                            <?= nl2br(e($event['description'])) ?>
                        </div>
                    <?php endif; ?>

                    <div class="event-detail__actions">
                        <?php if ($user): ?>
                            <?php if (!$isAttendee && !$isPast): ?>
                                <form method="post" action="/?page=event_view&id=<?= (int) $id ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="rsvp_action" value="rsvp">
                                    <button type="submit" class="btn btn--primary">RSVP</button>
                                </form>
                            <?php elseif ($isAttendee && !$isPast): ?>
                                <form method="post" action="/?page=event_view&id=<?= (int) $id ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="rsvp_action" value="cancel">
                                    <button type="submit" class="btn btn--ghost">Cancel RSVP</button>
                                </form>
                            <?php elseif ($isAttendee && $isPast): ?>
                                <p class="event-attended-note">You attended this event.</p>
                            <?php endif; ?>
                            <?php if ($isEventOwner || $isGroupOwner): ?>
                                <a href="/?page=event_edit&id=<?= (int) $id ?>" class="btn btn--ghost">Edit event</a>
                                <form method="post" action="/?page=event_delete">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="event_id" value="<?= (int) $id ?>">
                                    <button type="submit" class="btn btn--danger">Delete event</button>
                                </form>
                            <?php endif; ?>
                        <?php elseif (!$isPast): ?>
                            <a href="/?page=signin" class="btn btn--primary">Sign in to RSVP</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($isAttendee && !$isMember): ?>
                        <div class="event-join-prompt">
                            <p>Want to stay connected? Join the group to get future events.</p>
                            <form method="post" action="/?page=group_view&id=<?= (int) $event['group_id'] ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="membership_action" value="join">
                                <button type="submit" class="btn btn--primary btn--sm">Join <?= e($event['group_name']) ?></button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="event-detail__aside">
                <div class="event-attendees">
                    <h2 class="event-attendees__heading">
                        <?= $rsvpCount ?> <?= $rsvpCount === 1 ? 'attendee' : 'attendees' ?>
                    </h2>
                    <?php if (empty($attendees)): ?>
                        <p class="event-attendees__empty">No RSVPs yet.</p>
                    <?php else: ?>
                        <ul class="event-attendees__list">
                            <?php foreach ($attendees as $attendee): ?>
                                <li class="attendee-card">
                                    <span class="attendee-card__name"><?= e($attendee['name']) ?></span>
                                    <?php if ($attendee['is_member']): ?>
                                        <span class="attendee-card__badge attendee-card__badge--member">Member</span>
                                    <?php else: ?>
                                        <span class="attendee-card__badge attendee-card__badge--guest">Guest</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>
</section>
<?php
render($event['title'] . ' — Kai');
