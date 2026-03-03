<?php
declare(strict_types=1);

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/?page=groups');
}

csrf_verify();

$pdo      = get_db();
$event_id = (int) ($_POST['event_id'] ?? 0);

if ($event_id === 0) {
    redirect('/?page=groups');
}

$stmt = $pdo->prepare("
    SELECT e.id, e.group_id, e.creator_id, g.creator_id AS group_creator_id
    FROM group_events e
    JOIN user_groups g ON g.id = e.group_id
    WHERE e.id = ?
");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

$currentUser = current_user();

if (!$event ||
    ((int) $event['creator_id'] !== (int) $currentUser['id'] &&
     (int) $event['group_creator_id'] !== (int) $currentUser['id'])) {
    flash('error', 'Event not found or you do not have permission to delete it.');
    redirect('/?page=groups');
}

$pdo->prepare('DELETE FROM group_events WHERE id = ?')->execute([$event_id]);

flash('success', 'Event deleted.');
redirect('/?page=group_view&id=' . $event['group_id']);
