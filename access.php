<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function client_is_visible_to_current_user(int $clientId): bool
{
    $user = $_SESSION['user'];

    if ($user['role'] === 'admin') {
        return true;
    }

    if ($user['role'] === 'client') {
        $stmt = db()->prepare('SELECT 1 FROM clients WHERE id = ? AND user_id = ? AND active = 1');
        $stmt->execute([$clientId, $user['id']]);
        return (bool) $stmt->fetchColumn();
    }

    $stmt = db()->prepare('SELECT 1 FROM client_employees WHERE client_id = ? AND employee_id = ?');
    $stmt->execute([$clientId, $user['id']]);
    return (bool) $stmt->fetchColumn();
}

function require_client_access(int $clientId): void
{
    require_login();

    if (!client_is_visible_to_current_user($clientId)) {
        http_response_code(403);
        exit('You do not have access to this client.');
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
