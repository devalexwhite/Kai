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

$color = group_color((int) $group['id']);

ob_start();
?>
<section class="group-detail">
    <div class="container">
        <div class="group-detail__card">
            <div class="group-detail__cover" style="background: <?= $color ?>;" aria-hidden="true"></div>
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
    </div>
</section>
<?php
render($group['name'] . ' — Kai');
