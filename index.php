<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

header('Location: ' . dashboard_for_role($_SESSION['user']['role']));
exit;
