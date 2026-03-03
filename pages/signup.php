<?php
declare(strict_types=1);

// Redirect logged-in users away from the signup page
if (current_user() !== null) {
    redirect('/?page=dashboard');
}

$errors = [];
$old    = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    $old = ['name' => $name, 'email' => $email];

    if (mb_strlen($name) < 2) {
        $errors['name'] = 'Name must be at least 2 characters.';
    } elseif (mb_strlen($name) > 100) {
        $errors['name'] = 'Name must be under 100 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (mb_strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }

    if (empty($errors)) {
        try {
            $userId = register_user($name, $email, $password);
            login_user($userId);
            if ($remember) {
                set_remember_me_cookie($userId);
            }
            flash('success', 'Welcome to Kai, ' . e($name) . '!');
            redirect('/?page=dashboard');
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE constraint')) {
                $errors['email'] = 'An account with this email already exists.';
            } else {
                throw $e;
            }
        }
    }
}

ob_start();
?>
<section class="auth-page">
    <div class="auth-card">
        <h1 class="auth-card__title">Create your account</h1>
        <p class="auth-card__sub">Already have an account? <a href="/?page=signin">Sign in</a></p>

        <form method="post" action="/?page=signup" novalidate>
            <?= csrf_field() ?>

            <div class="form-group <?= !empty($errors['name']) ? 'form-group--error' : '' ?>">
                <label for="name">Full name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?= e($old['name']) ?>"
                    autocomplete="name"
                    required
                    <?= !empty($errors['name']) ? 'aria-describedby="name-error"' : '' ?>
                >
                <?php if (!empty($errors['name'])): ?>
                    <span class="form-error" id="name-error"><?= e($errors['name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group <?= !empty($errors['email']) ? 'form-group--error' : '' ?>">
                <label for="email">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($old['email']) ?>"
                    autocomplete="email"
                    required
                    <?= !empty($errors['email']) ? 'aria-describedby="email-error"' : '' ?>
                >
                <?php if (!empty($errors['email'])): ?>
                    <span class="form-error" id="email-error"><?= e($errors['email']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group <?= !empty($errors['password']) ? 'form-group--error' : '' ?>">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    minlength="6"
                    required
                    <?= !empty($errors['password']) ? 'aria-describedby="password-error"' : '' ?>
                >
                <span class="form-hint">Minimum 6 characters</span>
                <?php if (!empty($errors['password'])): ?>
                    <span class="form-error" id="password-error"><?= e($errors['password']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group form-group--checkbox">
                <label>
                    <input type="checkbox" name="remember" value="1">
                    Remember me for 30 days
                </label>
            </div>

            <button type="submit" class="btn btn--primary btn--full">Create account</button>
        </form>
    </div>
</section>
<?php
render('Sign Up — Kai');
