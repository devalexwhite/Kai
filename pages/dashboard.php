<?php
declare(strict_types=1);

require_auth();

$user = current_user();

ob_start();
?>
<section class="dashboard">
    <div class="container">
        <div class="dashboard__header">
            <div>
                <h1 class="dashboard__greeting">Welcome back, <?= e($user['name']) ?>!</h1>
                <p class="dashboard__date"><?= date('l, F j, Y') ?></p>
            </div>
        </div>

        <div class="dashboard__stats">
            <div class="stat-card">
                <span class="stat-card__value">0</span>
                <span class="stat-card__label">Upcoming events</span>
            </div>
            <div class="stat-card">
                <span class="stat-card__value">0</span>
                <span class="stat-card__label">Groups joined</span>
            </div>
            <div class="stat-card">
                <span class="stat-card__value">0</span>
                <span class="stat-card__label">Events attended</span>
            </div>
        </div>
    </div>

    <div class="container">
        <section class="dashboard-section">
            <div class="dashboard-section__header">
                <h2>Your upcoming events</h2>
                <a href="#" class="btn btn--ghost btn--sm">Browse events</a>
            </div>
            <div class="placeholder-list">
                <div class="placeholder-card placeholder-card--empty">
                    <p>You haven&rsquo;t RSVP&rsquo;d to any events yet.</p>
                    <a href="#" class="btn btn--primary btn--sm">Find events near you</a>
                </div>
            </div>
        </section>

        <section class="dashboard-section">
            <div class="dashboard-section__header">
                <h2>Your groups</h2>
                <a href="#" class="btn btn--ghost btn--sm">Explore groups</a>
            </div>
            <div class="group-grid">
                <div class="placeholder-card placeholder-card--empty">
                    <p>You haven&rsquo;t joined any groups yet.</p>
                    <a href="#" class="btn btn--primary btn--sm">Find groups</a>
                </div>
            </div>
        </section>

        <section class="dashboard-section">
            <div class="dashboard-section__header">
                <h2>Suggested for you</h2>
            </div>
            <div class="group-grid">
                <article class="group-card group-card--placeholder">
                    <div class="group-card__cover" aria-hidden="true" style="background: #ddd6fe;"></div>
                    <div class="group-card__body">
                        <h3>Tech &amp; Code</h3>
                        <p>12 upcoming events &middot; 340 members</p>
                        <a href="#" class="btn btn--ghost btn--sm">View group</a>
                    </div>
                </article>
                <article class="group-card group-card--placeholder">
                    <div class="group-card__cover" aria-hidden="true" style="background: #bbf7d0;"></div>
                    <div class="group-card__body">
                        <h3>Outdoor Adventures</h3>
                        <p>8 upcoming events &middot; 215 members</p>
                        <a href="#" class="btn btn--ghost btn--sm">View group</a>
                    </div>
                </article>
                <article class="group-card group-card--placeholder">
                    <div class="group-card__cover" aria-hidden="true" style="background: #fed7aa;"></div>
                    <div class="group-card__body">
                        <h3>Book Club</h3>
                        <p>2 upcoming events &middot; 89 members</p>
                        <a href="#" class="btn btn--ghost btn--sm">View group</a>
                    </div>
                </article>
            </div>
        </section>
    </div>
</section>
<?php
render('Dashboard — Kai');
