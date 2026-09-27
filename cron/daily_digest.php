<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/notifications.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$pdo = db();
$employees = $pdo->query("SELECT id, name, email FROM users WHERE role = 'employee' AND active = 1 AND email <> '' ORDER BY id")->fetchAll();
$summary = [];
foreach ($employees as $employee) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status <> 'completed'");
    $q->execute([$employee['id']]); $pending = (int) $q->fetchColumn();
    $q = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status <> 'completed' AND due_date < CURDATE()");
    $q->execute([$employee['id']]); $overdue = (int) $q->fetchColumn();
    $q = $pdo->prepare("SELECT COUNT(*) FROM content WHERE employee_id = ? AND status = 'changes_requested'");
    $q->execute([$employee['id']]); $changes = (int) $q->fetchColumn();
    $body = "Hello {$employee['name']},\n\nDaily work digest:\nPending tasks: {$pending}\nOverdue tasks: {$overdue}\nPending change requests: {$changes}\n\nPlease open your employee dashboard for details.";
    if (!$dryRun) { send_notification((string) $employee['email'], 'Daily work digest', $body); }
    $summary[] = $employee['email'] . ': pending=' . $pending . ', overdue=' . $overdue . ', changes=' . $changes;
}
echo implode(PHP_EOL, $summary) . (count($summary) ? PHP_EOL : '');
