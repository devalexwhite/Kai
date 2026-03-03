<?php
declare(strict_types=1);

require_auth();

$pdo      = get_db();
$group_id = (int) ($_GET['group_id'] ?? 0);

if ($group_id === 0) {
    redirect('/?page=groups');
}

$stmt = $pdo->prepare('SELECT id, name, creator_id FROM user_groups WHERE id = ?');
$stmt->execute([$group_id]);
$group = $stmt->fetch();

if (!$group) {
    redirect('/?page=groups');
}

if ((int) $group['creator_id'] !== (int) current_user()['id']) {
    flash('error', 'Only the group owner can create events.');
    redirect('/?page=group_view&id=' . $group_id);
}

$errors = [];
$old    = [
    'title'       => '',
    'description' => '',
    'event_date'  => '',
    'event_time'  => '',
    'location'    => '',
    'meeting_url' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date  = trim($_POST['event_date'] ?? '');
    $event_time  = trim($_POST['event_time'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $meeting_url = trim($_POST['meeting_url'] ?? '');

    $old = compact('title', 'description', 'event_date', 'event_time', 'location', 'meeting_url');

    if (mb_strlen($title) < 2) {
        $errors['title'] = 'Event title must be at least 2 characters.';
    } elseif (mb_strlen($title) > 120) {
        $errors['title'] = 'Event title must be under 120 characters.';
    }

    if (mb_strlen($description) > 2000) {
        $errors['description'] = 'Description must be under 2000 characters.';
    }

    if ($event_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
        $errors['event_date'] = 'Please enter a valid date.';
    }

    if ($event_time === '' || !preg_match('/^\d{2}:\d{2}$/', $event_time)) {
        $errors['event_time'] = 'Please enter a valid time.';
    }

    if (mb_strlen($location) > 200) {
        $errors['location'] = 'Location must be under 200 characters.';
    }

    if ($meeting_url !== '') {
        $scheme = strtolower(substr($meeting_url, 0, 8));
        if (!str_starts_with($scheme, 'http://') && !str_starts_with($scheme, 'https://')) {
            $errors['meeting_url'] = 'Meeting URL must start with http:// or https://.';
        } elseif (!filter_var($meeting_url, FILTER_VALIDATE_URL)) {
            $errors['meeting_url'] = 'Please enter a valid URL.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('
            INSERT INTO group_events (group_id, creator_id, title, description, event_date, event_time, location, meeting_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $group_id,
            current_user()['id'],
            $title,
            $description,
            $event_date,
            $event_time,
            $location,
            $meeting_url,
        ]);
        $eventId = (int) $pdo->lastInsertId();

        flash('success', 'Event created!');
        redirect('/?page=event_view&id=' . $eventId);
    }
}

ob_start();
?>
<section class="form-page">
    <div class="container">
        <div class="form-card">
            <h1>Create an event</h1>
            <p class="form-card__sub"><a href="/?page=group_view&id=<?= (int) $group['id'] ?>">← Back to <?= e($group['name']) ?></a></p>

            <form method="post" action="/?page=event_create&group_id=<?= (int) $group_id ?>" novalidate>
                <?= csrf_field() ?>

                <div class="form-group <?= !empty($errors['title']) ? 'form-group--error' : '' ?>">
                    <label for="title">Event title</label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        value="<?= e($old['title']) ?>"
                        maxlength="120"
                        required
                        <?= !empty($errors['title']) ? 'aria-describedby="title-error"' : '' ?>
                    >
                    <?php if (!empty($errors['title'])): ?>
                        <span class="form-error" id="title-error"><?= e($errors['title']) ?></span>
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

                <div class="form-row">
                    <div class="form-group <?= !empty($errors['event_date']) ? 'form-group--error' : '' ?>">
                        <label for="event_date">Date</label>
                        <input
                            type="date"
                            id="event_date"
                            name="event_date"
                            value="<?= e($old['event_date']) ?>"
                            required
                            <?= !empty($errors['event_date']) ? 'aria-describedby="event_date-error"' : '' ?>
                        >
                        <?php if (!empty($errors['event_date'])): ?>
                            <span class="form-error" id="event_date-error"><?= e($errors['event_date']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group <?= !empty($errors['event_time']) ? 'form-group--error' : '' ?>">
                        <label for="event_time">Time</label>
                        <input
                            type="time"
                            id="event_time"
                            name="event_time"
                            value="<?= e($old['event_time']) ?>"
                            required
                            <?= !empty($errors['event_time']) ? 'aria-describedby="event_time-error"' : '' ?>
                        >
                        <?php if (!empty($errors['event_time'])): ?>
                            <span class="form-error" id="event_time-error"><?= e($errors['event_time']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group <?= !empty($errors['location']) ? 'form-group--error' : '' ?>">
                    <label for="location">Location <span class="form-hint">(in-person venue — optional)</span></label>
                    <input
                        type="text"
                        id="location"
                        name="location"
                        value="<?= e($old['location']) ?>"
                        maxlength="200"
                        placeholder="e.g. Central Park, New York"
                        <?= !empty($errors['location']) ? 'aria-describedby="location-error"' : '' ?>
                    >
                    <?php if (!empty($errors['location'])): ?>
                        <span class="form-error" id="location-error"><?= e($errors['location']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group <?= !empty($errors['meeting_url']) ? 'form-group--error' : '' ?>">
                    <label for="meeting_url">Meeting URL <span class="form-hint">(remote link — optional)</span></label>
                    <input
                        type="url"
                        id="meeting_url"
                        name="meeting_url"
                        value="<?= e($old['meeting_url']) ?>"
                        maxlength="500"
                        placeholder="https://..."
                        <?= !empty($errors['meeting_url']) ? 'aria-describedby="meeting_url-error"' : '' ?>
                    >
                    <?php if (!empty($errors['meeting_url'])): ?>
                        <span class="form-error" id="meeting_url-error"><?= e($errors['meeting_url']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">Create event</button>
                    <a href="/?page=group_view&id=<?= (int) $group_id ?>" class="btn btn--ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>
<?php
render('Create Event — Kai');
