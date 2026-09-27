<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function is_logged_in(): bool
{
    if (!isset($_SESSION['user'])) {
        return false;
    }
    $lastActivity = (int) ($_SESSION['last_activity'] ?? time());
    if ($lastActivity + 1800 < time()) {
        unset($_SESSION['user'], $_SESSION['last_activity']);
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_role(string $role): void
{
    require_login();

    if ($_SESSION['user']['role'] !== $role) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function available_permissions(): array
{
    return [
        'manage_plans' => 'Create and manage client plans',
        'upload_content' => 'Upload content',
        'view_clients' => 'View assigned client profiles',
        'view_tasks' => 'View and update tasks',
        'view_payslip' => 'View own payslips',
        'apply_leave' => 'Apply for leave',
        'view_activity' => 'View own activity',
    ];
}

function has_permission(string $permission): bool
{
    if (!is_logged_in()) {
        return false;
    }
    if ($_SESSION['user']['role'] === 'admin') {
        return true;
    }
    $permissionStmt = db()->prepare('SELECT enabled FROM user_permissions WHERE user_id = ? AND permission_key = ? LIMIT 1');
    $permissionStmt->execute([$_SESSION['user']['id'], $permission]);
    $stored = $permissionStmt->fetchColumn();
    if ($stored !== false) {
        return (int) $stored === 1;
    }
    $raw = $_SESSION['user']['permissions'] ?? null;
    $permissions = $raw ? json_decode((string) $raw, true) : null;
    return !is_array($permissions) || in_array($permission, $permissions, true);
}

function require_permission(string $permission): void
{
    require_login();
    if (!has_permission($permission)) {
        http_response_code(403);
        exit('You do not have permission for this page.');
    }
}

function dashboard_for_role(string $role): string
{
    return match ($role) {
        'admin' => 'admin_dashboard.php',
        'employee' => 'employee_dashboard.php',
        'client' => 'client_dashboard.php',
        default => 'login.php',
    };
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(419);
        exit('Invalid form token. Please go back and try again.');
    }
}
