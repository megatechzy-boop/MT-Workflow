<?php
declare(strict_types=1);
require_once __DIR__ . '/access.php';
require_role('employee');
require_permission('view_tasks');
require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/employee_ui.php';

$pdo = db();
$userId = (int) $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? 'status');
    $owned = $pdo->prepare('SELECT id FROM tasks WHERE id = ? AND assigned_to = ?');
    $owned->execute([$id, $userId]);
    if (!$owned->fetchColumn()) {
        http_response_code(403);
        exit('Task not found or not assigned to you.');
    }
    if ($action === 'start') {
        $active = $pdo->prepare('SELECT id FROM time_logs WHERE task_id = ? AND employee_id = ? AND end_time IS NULL LIMIT 1');
        $active->execute([$id, $userId]);
        if (!$active->fetchColumn()) {
            $pdo->prepare('INSERT INTO time_logs (task_id, employee_id, start_time, date) VALUES (?, ?, NOW(), CURDATE())')->execute([$id, $userId]);
        }
    } elseif ($action === 'stop') {
        $active = $pdo->prepare('SELECT id, start_time FROM time_logs WHERE task_id = ? AND employee_id = ? AND end_time IS NULL ORDER BY id DESC LIMIT 1');
        $active->execute([$id, $userId]);
        if ($log = $active->fetch()) {
            $pdo->prepare('UPDATE time_logs SET end_time = NOW(), duration = GREATEST(0, TIMESTAMPDIFF(SECOND, start_time, NOW())) WHERE id = ?')->execute([$log['id']]);
        }
    } else {
        $status = (string) ($_POST['status'] ?? '');
        if (in_array($status, ['pending', 'in_progress', 'completed'], true)) {
            $pdo->prepare('UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?')->execute([$status, $id, $userId]);
            log_activity($userId, 'Updated task', 'task', $id);
        }
    }
    header('Location: my_tasks.php');
    exit;
}

$stmt = $pdo->prepare('SELECT t.*, c.name AS client_name,
    COALESCE((SELECT SUM(duration) FROM time_logs tl WHERE tl.task_id = t.id AND tl.employee_id = t.assigned_to), 0) AS time_spent,
    EXISTS(SELECT 1 FROM time_logs active_log WHERE active_log.task_id = t.id AND active_log.employee_id = t.assigned_to AND active_log.end_time IS NULL) AS timer_running
    FROM tasks t INNER JOIN clients c ON c.id = t.client_id
    WHERE t.assigned_to = ? ORDER BY t.due_date IS NULL, t.due_date, t.created_at DESC');
$stmt->execute([$userId]);
$tasks = $stmt->fetchAll();
$todayStmt = $pdo->prepare('SELECT COALESCE(SUM(duration), 0) FROM time_logs WHERE employee_id = ? AND date = CURDATE()');
$todayStmt->execute([$userId]);
$todaySeconds = (int) $todayStmt->fetchColumn();
function format_duration(int $seconds): string { return sprintf('%dh %02dm', intdiv($seconds, 3600), intdiv($seconds % 3600, 60)); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>My Tasks</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/saas.css"></head><body class="bg-light"><main class="container py-4"><div class="d-flex justify-content-between"><div><h1 class="h3">My Tasks</h1><p class="text-muted mb-0">Today tracked: <?= e(format_duration($todaySeconds)) ?></p></div><a href="employee_dashboard.php">Dashboard</a></div><div class="card card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Task</th><th>Client</th><th>Due</th><th>Status</th><th>Time</th><th>Timer</th></tr></thead><tbody><?php if (!$tasks): ?><tr><td colspan="6" class="text-center text-muted py-4">No tasks assigned yet.</td></tr><?php else: foreach ($tasks as $task): $overdue = !empty($task['due_date']) && $task['due_date'] < date('Y-m-d') && $task['status'] !== 'completed'; ?><tr class="<?= $overdue ? 'table-danger' : '' ?>"><td><?= e($task['title']) ?><br><small><?= e($task['description'] ?? '') ?></small><?php if ($overdue): ?><br><small class="text-danger fw-semibold">Overdue</small><?php endif; ?></td><td><?= e($task['client_name']) ?></td><td><?= e($task['due_date'] ?? '') ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $task['id'] ?>"><select class="form-select form-select-sm" name="status" onchange="this.form.submit()"><option value="pending" <?= $task['status'] === 'pending' ? 'selected' : '' ?>>Pending</option><option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>In progress</option><option value="completed" <?= $task['status'] === 'completed' ? 'selected' : '' ?>>Completed</option></select></form></td><td><?= e(format_duration((int) $task['time_spent'])) ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $task['id'] ?>"><input type="hidden" name="action" value="<?= $task['timer_running'] ? 'stop' : 'start' ?>"><button class="btn btn-sm <?= $task['timer_running'] ? 'btn-danger' : 'btn-primary' ?>"><?= $task['timer_running'] ? 'Stop' : 'Start' ?></button></form></td></tr><?php endforeach; endif; ?></tbody></table></div></div></main></body></html>
