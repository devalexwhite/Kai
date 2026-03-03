<?php
declare(strict_types=1);

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/?page=groups');
}

csrf_verify();

$pdo     = get_db();
$user    = current_user();
$groupId = (int) ($_POST['group_id'] ?? 0);

$groupStmt = $pdo->prepare('SELECT id FROM user_groups WHERE id = ?');
$groupStmt->execute([$groupId]);

if (!$groupStmt->fetch()) {
    redirect('/?page=groups');
}

$stmt = $pdo->prepare('INSERT OR IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)');
$stmt->execute([$groupId, (int) $user['id']]);

if (!is_htmx()) {
    redirect('/?page=group_view&id=' . $groupId);
}

?>
<div id="group-actions-<?= (int) $groupId ?>" class="group-card__actions group-card__actions--joined">
    <span class="group-card__joined-badge">Joined!</span>
    <a href="/?page=group_view&id=<?= (int) $groupId ?>" class="btn btn--ghost btn--sm">View group</a>
</div>
<?php
exit;
