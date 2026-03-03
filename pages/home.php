<?php
declare(strict_types=1);

ob_start();
?>
<section class="hero">
    <div class="container">
        <h1 class="hero__headline">Find your people.<br>Build your community.</h1>
        <p class="hero__sub">Kai brings people together through local events, shared interests, and the groups that make life richer.</p>
        <div class="hero__actions">
            <?php if (current_user()): ?>
                <a href="/?page=dashboard" class="btn btn--primary btn--lg">Go to Dashboard</a>
            <?php else: ?>
                <a href="/?page=signup" class="btn btn--primary btn--lg">Get started — it&rsquo;s free</a>
                <a href="/?page=signin" class="btn btn--ghost btn--lg">Sign in</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="how-it-works">
    <div class="container">
        <h2 class="section-title">How Kai works</h2>
        <ol class="steps">
            <li class="step">
                <span class="step__number">1</span>
                <h3>Create an account</h3>
                <p>Sign up in seconds. No credit card, no noise &mdash; just you and your community.</p>
            </li>
            <li class="step">
                <span class="step__number">2</span>
                <h3>Join groups</h3>
                <p>Discover groups organised around your interests, neighbourhood, or profession.</p>
            </li>
            <li class="step">
                <span class="step__number">3</span>
                <h3>Attend events</h3>
                <p>RSVP for meetups, workshops, and gatherings happening near you every week.</p>
            </li>
        </ol>
    </div>
</section>

<section class="features">
    <div class="container">
        <h2 class="section-title">Everything you need to connect</h2>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-card__icon" aria-hidden="true">&#128101;</div>
                <h3>Groups</h3>
                <p>Join or create groups around any topic. Hiking, coding, photography, cooking &mdash; find your niche.</p>
            </div>
            <div class="feature-card">
                <div class="feature-card__icon" aria-hidden="true">&#128197;</div>
                <h3>Events</h3>
                <p>Browse upcoming events in your area. Online and in-person. One-offs and recurring.</p>
            </div>
            <div class="feature-card">
                <div class="feature-card__icon" aria-hidden="true">&#127758;</div>
                <h3>Local focus</h3>
                <p>Kai puts your city first. Real connections start with real proximity.</p>
            </div>
        </div>
    </div>
</section>

<section class="cta-band">
    <div class="container">
        <h2>Ready to get started?</h2>
        <p>Join thousands of people building real connections through Kai.</p>
        <?php if (!current_user()): ?>
            <a href="/?page=signup" class="btn btn--primary btn--lg">Create your free account</a>
        <?php else: ?>
            <a href="/?page=dashboard" class="btn btn--primary btn--lg">Go to Dashboard</a>
        <?php endif; ?>
    </div>
</section>
<?php
render('Kai — Find your people');
