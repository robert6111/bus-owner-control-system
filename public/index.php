<?php
require_once __DIR__ . '/../app/core/auth.php';
if (current_user()) {
    redirect('dashboard.php');
}
redirect('login.php');
