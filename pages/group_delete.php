<?php
declare(strict_types=1);

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/?page=groups');
}

csrf_verify();

$pdo      = get_db();
$group_id = (int) ($_POST['group_id'] ?? 0);

if ($group_id === 0) {
    redirect('/?page=groups');
}

$stmt = $pdo->prepare('SELECT id, name, creator_id FROM user_groups WHERE id = ?');
$stmt->execute([$group_id]);
$group = $stmt->fetch();

if (!$group || (int) $group['creator_id'] !== (int) current_user()['id']) {
    flash('error', 'Group not found or you do not have permission to delete it.');
    redirect('/?page=groups');
}

$pdo->prepare('DELETE FROM user_groups WHERE id = ?')->execute([$group_id]);

flash('success', 'Group deleted.');
redirect('/?page=groups');
