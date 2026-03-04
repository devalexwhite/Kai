<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Kai') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Shippori+Mincho+B1:wght@400;600;800&family=Lora:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/css/app.css">
</head>
<!-- hx-headers sends the CSRF token with every HTMX request automatically -->
<body hx-headers='{"X-CSRF-Token": "<?= e(csrf_token()) ?>"}'>

    <?php include __DIR__ . '/nav.php'; ?>

    <?php foreach (['success', 'error', 'info'] as $_flash_type): ?>
        <?php if ($msg = get_flash($_flash_type)): ?>
            <div class="flash flash--<?= $_flash_type ?>" role="alert"><?= e($msg) ?></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <main>
        <?= $content ?? '' ?>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Kai &mdash; Find your people.</p>
        </div>
    </footer>

    <script src="/public/htmx.min.js"></script>
</body>
</html>
