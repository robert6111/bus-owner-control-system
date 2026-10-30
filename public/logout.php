<?php
require_once __DIR__ . '/../app/core/auth.php';
logout_user();
session_name(SESSION_NAME);
session_start();
flash('success', 'You have been logged out.');
redirect('login.php');
