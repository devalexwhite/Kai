<?php
declare(strict_types=1);

require_auth();

$pdo    = get_db();
$cities = $pdo->query('SELECT id, name, state FROM cities ORDER BY state, name')->fetchAll();

$errors = [];
$old    = ['name' => '', 'description' => '', 'city_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $city_id     = (int) ($_POST['city_id'] ?? 0);
    $old         = ['name' => $name, 'description' => $description, 'city_id' => (string) $city_id];

    if (mb_strlen($name) < 2) {
        $errors['name'] = 'Group name must be at least 2 characters.';
    } elseif (mb_strlen($name) > 100) {
        $errors['name'] = 'Group name must be under 100 characters.';
    }

    if (mb_strlen($description) > 2000) {
        $errors['description'] = 'Description must be under 2000 characters.';
    }

    if ($city_id === 0) {
        $errors['city_id'] = 'Please select a city.';
    } else {
        $cityCheck = $pdo->prepare('SELECT id FROM cities WHERE id = ?');
        $cityCheck->execute([$city_id]);
        if (!$cityCheck->fetch()) {
            $errors['city_id'] = 'Please select a valid city.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO user_groups (name, description, city_id, creator_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $description, $city_id, current_user()['id']]);
        $groupId = (int) $pdo->lastInsertId();

        // Creator automatically joins their own group
        $pdo->prepare('INSERT OR IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)')->execute([$groupId, current_user()['id']]);

        flash('success', 'Your group has been created!');
        redirect('/?page=group_view&id=' . $groupId);
    }
}

// Resolve preselected city for widget (set after a failed submission that had a city chosen)
$preselected = $old['city_id'] ?: current_user()['city_id'];

$cityStmt = $pdo->prepare('SELECT id, name, state FROM cities WHERE id = ?');
$cityStmt->execute([$preselected]);
$preselected = $cityStmt->fetch() ?: null;


ob_start();
?>
<section class="form-page">
    <div class="container">
        <div class="form-card">
            <h1>Start a group</h1>
            <p class="form-card__sub">Bring people together around something you care about.</p>

            <form method="post" action="/?page=group_create" novalidate>
                <?= csrf_field() ?>

                <div class="form-group <?= !empty($errors['name']) ? 'form-group--error' : '' ?>">
                    <label for="name">Group name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= e($old['name']) ?>"
                        maxlength="100"
                        required
                        <?= !empty($errors['name']) ? 'aria-describedby="name-error"' : '' ?>
                    >
                    <?php if (!empty($errors['name'])): ?>
                        <span class="form-error" id="name-error"><?= e($errors['name']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group <?= !empty($errors['description']) ? 'form-group--error' : '' ?>">
                    <label for="description">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        maxlength="2000"
                        <?= !empty($errors['description']) ? 'aria-describedby="description-error"' : '' ?>
                    ><?= e($old['description']) ?></textarea>
                    <span class="form-hint">Tell people what your group is about. Up to 2000 characters.</span>
                    <?php if (!empty($errors['description'])): ?>
                        <span class="form-error" id="description-error"><?= e($errors['description']) ?></span>
                    <?php endif; ?>
                </div>

                <?php include __DIR__ . '/../templates/city_widget.php'; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">Create group</button>
                    <a href="/?page=groups" class="btn btn--ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>
<?php
render('Start a Group — Kai');
