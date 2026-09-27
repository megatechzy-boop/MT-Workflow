<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (!defined('SAAS_NO_GLOBAL_NAV')) {
    $currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
    $financeNav = [
        'admin_dashboard.php' => ['▦', 'Overview'],
        'manage_clients.php' => ['♧', 'Clients'],
        'manage_users.php' => ['♙', 'Users'],
        'finance_dashboard.php' => ['◈', 'Finance'],
        'staff_master.php' => ['♙', 'Staff'],
        'admin_attendance.php' => ['◷', 'Attendance'],
        'payroll.php' => ['▤', 'Payroll'],
        'invoices.php' => ['▧', 'Invoices'],
        'expenses.php' => ['▣', 'Expenses'],
        'cashflow.php' => ['↗', 'Cash flow'],
        'employee_plans.php' => ['☷', 'Plans'],
        'employee_client_report.php' => ['▥', 'Contribution report'],
        'leaderboard.php' => ['★', 'Leaderboard'],
        'profile_update_requests.php' => ['✎', 'Profile requests'],
    ];
    $navHtml = '';
    foreach ($financeNav as $page => [$icon, $label]) {
        $active = $page === $currentPage ? ' active' : '';
        $navHtml .= '<a class="saas-nav' . $active . '" href="' . htmlspecialchars($page, ENT_QUOTES, 'UTF-8') . '">' . $icon . ' <span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></a>';
    }
    $navHtml .= '<a class="saas-nav" href="logout.php">↪ <span>Log out</span></a>';
    echo '<link rel="stylesheet" href="assets/css/saas.css"><link rel="stylesheet" href="assets/css/finance-global.css"><script src="assets/js/session-timeout.js" defer></script><aside class="finance-global-sidebar"><div class="brand"><span>MT</span> Mega Techzy</div>' . $navHtml . '</aside>';
}
echo '<script src="assets/js/responsive-nav.js?v=2" defer></script>';
echo '<script src="assets/js/onesignal.js?v=1" defer></script>';

function inr(float|int|string $amount): string
{
    return '₹' . number_format((float) $amount, 2);
}

function require_finance_admin(): void
{
    require_role('admin');
}
