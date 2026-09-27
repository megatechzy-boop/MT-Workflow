<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function log_activity(?int $employeeId, string $action, string $referenceType = 'other', ?int $referenceId = null): void
{
    db()->prepare('INSERT INTO activity_log (employee_id, action, reference_type, reference_id) VALUES (?, ?, ?, ?)')->execute([$employeeId, $action, $referenceType, $referenceId]);
}
