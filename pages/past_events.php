<?php
declare(strict_types=1);

// This endpoint serves HTMX fragment requests.
// Non-HTMX browsers are redirected to the group page with show_past=1 for the full-page fallback.
if (!is_htmx()) {
    $group_id = (int) ($_GET['group_id'] ?? 0);
    redirect($group_id > 0 ? '/?page=group_view&id=' . $group_id . '&show_past=1' : '/?page=groups');
}

$pdo      = get_db();
$group_id = (int) ($_GET['group_id'] ?? 0);

if ($group_id === 0) {
    echo '<div id="past-events-section"></div>';
    exit;
}

$stmt = $pdo->prepare("
    SELECT e.id, e.title, e.event_date, e.event_time, e.location, e.meeting_url,
           COUNT(r.id) AS rsvp_count
    FROM group_events e
    LEFT JOIN event_rsvps r ON r.event_id = e.id
    WHERE e.group_id = ?
      AND (e.event_date < ? OR (e.event_date = ? AND e.event_time < ?))
    GROUP BY e.id
    ORDER BY e.event_date DESC, e.event_time DESC
");
$today = date('Y-m-d');
$now   = date('H:i');
$stmt->execute([$group_id, $today, $today, $now]);
$pastEvents = $stmt->fetchAll();

?>
<div id="past-events-section" class="group-events__past-list">
    <h3 class="group-events__past-heading">Past events</h3>
    <?php if (empty($pastEvents)): ?>
        <p class="group-events__no-past">No past events.</p>
    <?php else: ?>
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
    <?php endif; ?>
</div>
