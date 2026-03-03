<?php
declare(strict_types=1);

require_auth();

$pdo = get_db();
$id  = (int) ($_GET['id'] ?? 0);

if ($id === 0) {
    redirect('/?page=groups');
}

$stmt = $pdo->prepare('SELECT * FROM user_groups WHERE id = ?');
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

if ((int) $group['creator_id'] !== (int) current_user()['id']) {
    flash('error', 'You do not have permission to edit this group.');
    redirect('/?page=group_view&id=' . $id);
}

$cities = $pdo->query('SELECT id, name, state FROM cities ORDER BY state, name')->fetchAll();

$errors = [];
$old    = [
    'name'        => $group['name'],
    'description' => $group['description'],
    'city_id'     => (string) $group['city_id'],
];

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
        $pdo->prepare('UPDATE user_groups SET name = ?, description = ?, city_id = ? WHERE id = ?')
            ->execute([$name, $description, $city_id, $id]);
        flash('success', 'Group updated.');
        redirect('/?page=group_view&id=' . $id);
    }
}

// Resolve preselected city for widget (from current group data or failed POST)
$preselected = null;
if ((int) $old['city_id'] > 0) {
    $cityStmt = $pdo->prepare('SELECT id, name, state FROM cities WHERE id = ?');
    $cityStmt->execute([(int) $old['city_id']]);
    $preselected = $cityStmt->fetch() ?: null;
}

ob_start();
?>
<section class="form-page">
    <div class="container">
        <div class="form-card">
            <h1>Edit group</h1>
            <p class="form-card__sub"><a href="/?page=group_view&id=<?= (int) $id ?>">← Back to group</a></p>

            <form method="post" action="/?page=group_edit&id=<?= (int) $id ?>" novalidate>
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
                    <span class="form-hint">Up to 2000 characters.</span>
                    <?php if (!empty($errors['description'])): ?>
                        <span class="form-error" id="description-error"><?= e($errors['description']) ?></span>
                    <?php endif; ?>
                </div>

                <?php include __DIR__ . '/../templates/city_widget.php'; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">Save changes</button>
                    <a href="/?page=group_view&id=<?= (int) $id ?>" class="btn btn--ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>
<?php
render('Edit Group — Kai');
