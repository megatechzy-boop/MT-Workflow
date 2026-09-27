<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/activity.php';

if (!empty($_SESSION['user']) && $_SESSION['user']['role'] === 'employee') {
    $employeeId = (int) $_SESSION['user']['id'];
    db()->prepare('UPDATE attendance SET logout_time = NOW() WHERE employee_id = ? AND date = CURDATE()')->execute([$employeeId]);
    log_activity($employeeId, 'Logged out', 'login');
}

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
