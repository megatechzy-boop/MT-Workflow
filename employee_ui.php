<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if (!function_exists('inr')) {
    function inr(float|int|string $amount): string
    {
        return '₹' . number_format((float) $amount, 2);
    }
}

$employeePage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$employeeClientId = 0;
if (($_SESSION['user']['role'] ?? '') === 'employee') {
    $employeeClientStmt = db()->prepare('SELECT client_id FROM client_employees WHERE employee_id = ? ORDER BY client_id LIMIT 1');
    $employeeClientStmt->execute([(int) $_SESSION['user']['id']]);
    $employeeClientId = (int) ($employeeClientStmt->fetchColumn() ?: 0);
}
$employeeNav = [
    'employee_dashboard.php' => ['▦', 'Overview'],
    'my_tasks.php' => ['✓', 'My tasks'],
    'upload_content.php' => ['↑', 'Upload content'],
    'view_client_profile.php' => ['♙', 'Client profiles'],
    'employee_client_report.php' => ['▥', 'Contribution report'],
    'leaderboard.php' => ['★', 'Leaderboard'],
    'my_payslip.php' => ['▤', 'My payslips'],
    'apply_leave.php' => ['◷', 'Leave'],
    'activity_log.php' => ['◌', 'Activity'],
]; 
$employeeNavHtml = '';
foreach ($employeeNav as $page => [$icon, $label]) {
    if ($page === 'my_payslip.php') {
        $employeeNavHtml .= '<div class="employee-nav-label">Settings</div>';
    }
    $active = $page === $employeePage ? ' active' : '';
    $href = $page;
    if ($page === 'view_client_profile.php') {
        $href = 'employee_clients.php';
    }
    $employeeNavHtml .= '<a class="saas-nav' . $active . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $icon . ' <span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></a>';
}
$employeeNavHtml .= '<a class="saas-nav" href="logout.php">↪ <span>Log out</span></a>';

echo '<link rel="stylesheet" href="assets/css/saas.css"><link rel="stylesheet" href="assets/css/employee-global.css"><script src="assets/js/session-timeout.js" defer></script><aside class="employee-global-sidebar"><div class="brand"><span>MT</span> Mega Techzy</div>' . $employeeNavHtml . '</aside>';
