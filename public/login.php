<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/csrf.php';

if (current_user()) {
    redirect('dashboard.php');
}

if (is_post()) {
    verify_csrf();
    $email = strtolower(post_value('email'));
    $password = post_value('password');
    if (login_user($email, $password)) {
        redirect('dashboard.php');
    }
    flash('danger', 'Invalid email or password.');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="login-page">
    <div class="login-card">
        <h1>Bus Owner Control</h1>
        <p class="help-text">Internal operations, parcels, expenses and profit reports for bus owners.</p>
        <?php show_flash(); ?>
        <form method="post">
            <?= csrf_field() ?>
            <p>
                <label>Email</label>
                <input type="email" name="email" value="admin@buspro.local" required>
            </p>
            <p>
                <label>Password</label>
                <input type="password" name="password" autocomplete="current-password" required>
            </p>
            <button class="btn" type="submit">Login</button>
            <p class="help-text">Demo accounts are listed in the README (local testing only).</p>
        </form>
    </div>
</body>
</html>
