<?php declare(strict_types=1); ?>
<header class="site-header">
    <nav class="nav container">
        <a href="/" class="nav__brand">
            <img src="/public/images/logo.webp" width="50" height="50" />
        </a>
        <div class="nav__links">
            <?php $user = current_user(); ?>
            <?php if ($user): ?>
                <a href="/?page=dashboard">Dashboard</a>
                <a href="/?page=groups">Groups</a>
                <form method="post" action="/?page=signout" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost">Sign out</button>
                </form>
            <?php else: ?>
                <a href="/?page=signin">Sign in</a>
                <a href="/?page=signup" class="btn">Sign up</a>
            <?php endif; ?>
        </div>
    </nav>
</header>
