<?php
declare(strict_types=1);

require_auth();

$pdo = get_db();
$id  = (int) ($_GET['id'] ?? 0);

if ($id === 0) {
    redirect('/?page=groups');
}

$stmt = $pdo->prepare("
    SELECT e.*, g.name AS group_name, g.creator_id AS group_creator_id
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

$currentUser  = current_user();
$isEventOwner = ((int) $event['creator_id'] === (int) $currentUser['id']);
$isGroupOwner = ((int) $event['group_creator_id'] === (int) $currentUser['id']);

if (!$isEventOwner && !$isGroupOwner) {
    flash('error', 'You do not have permission to edit this event.');
    redirect('/?page=event_view&id=' . $id);
}

$errors = [];
$old    = [
    'title'       => $event['title'],
    'description' => $event['description'],
    'event_date'  => $event['event_date'],
    'event_time'  => $event['event_time'],
    'location'    => $event['location'],
    'meeting_url' => $event['meeting_url'],
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
        $pdo->prepare('
            UPDATE group_events
            SET title = ?, description = ?, event_date = ?, event_time = ?, location = ?, meeting_url = ?
            WHERE id = ?
        ')->execute([$title, $description, $event_date, $event_time, $location, $meeting_url, $id]);

        flash('success', 'Event updated.');
        redirect('/?page=event_view&id=' . $id);
    }
}

ob_start();
?>
<section class="form-page">
    <div class="container">
        <div class="form-card">
            <h1>Edit event</h1>
            <p class="form-card__sub"><a href="/?page=event_view&id=<?= (int) $id ?>">← Back to event</a></p>

            <form method="post" action="/?page=event_edit&id=<?= (int) $id ?>" novalidate>
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
                    <button type="submit" class="btn btn--primary">Save changes</button>
                    <a href="/?page=event_view&id=<?= (int) $id ?>" class="btn btn--ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>
<?php
render('Edit Event — Kai');
