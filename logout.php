<?php
// AA TRADERS - Sign Out
require_once __DIR__ . '/includes/auth.php';
Auth::logout();
header('Location: login.php');
exit;
