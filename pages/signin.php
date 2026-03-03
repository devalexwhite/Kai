<?php
declare(strict_types=1);

// Redirect logged-in users away from the sign-in page
if (current_user() !== null) {
    redirect('/?page=dashboard');
}

$error = null;
$old   = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    $old = ['email' => $email];

    $user = attempt_login($email, $password);

    if ($user) {
        login_user((int) $user['id']);
        if ($remember) {
            set_remember_me_cookie((int) $user['id']);
        }
        flash('success', 'Welcome back, ' . $user['name'] . '!');
        redirect('/?page=dashboard');
    } else {
        // Generic message to prevent user enumeration
        $error = 'Email or password is incorrect.';
    }
}

ob_start();
?>
<section class="auth-page">
    <div class="auth-card">
        <h1 class="auth-card__title">Sign in to Kai</h1>
        <p class="auth-card__sub">New here? <a href="/?page=signup">Create an account</a></p>

        <?php if ($error): ?>
            <div class="alert alert--error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/?page=signin" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($old['email']) ?>"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="form-group form-group--checkbox">
                <label>
                    <input type="checkbox" name="remember" value="1">
                    Remember me for 30 days
                </label>
            </div>

            <button type="submit" class="btn btn--primary btn--full">Sign in</button>
        </form>
    </div>
</section>
<?php
render('Sign In — Kai');
