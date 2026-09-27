<?php
declare(strict_types=1);
require_once __DIR__ . '/access.php';
require_login();
$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
if (!$isAdmin) {
    require_role('employee');
    require_permission('view_clients');
    require_once __DIR__ . '/employee_ui.php';
} else {
    require_once __DIR__ . '/finance.php';
}

$where = $isAdmin ? '' : 'INNER JOIN client_employees filter_ce ON filter_ce.client_id = c.id AND filter_ce.employee_id = :employee_id';
$sql = "SELECT c.id, c.name, c.industry,
    COUNT(DISTINCT content.id) AS uploads,
    COALESCE(ROUND(100 * SUM(content.status = 'approved') / NULLIF(SUM(content.status IN ('approved', 'changes_requested')), 0), 1), 0) AS approval_rate,
    COALESCE((SELECT SUM(tl.duration) FROM time_logs tl INNER JOIN tasks tt ON tt.id = tl.task_id WHERE tt.client_id = c.id), 0) AS time_spent,
    (SELECT COUNT(*) FROM tasks tc WHERE tc.client_id = c.id AND tc.status = 'completed') AS tasks_completed
    FROM clients c $where
    LEFT JOIN content ON content.client_id = c.id
    WHERE c.active = 1
    GROUP BY c.id, c.name, c.industry
    ORDER BY c.name";
$stmt = db()->prepare($sql);
if (!$isAdmin) { $stmt->bindValue(':employee_id', (int) $_SESSION['user']['id'], PDO::PARAM_INT); }
$stmt->execute();
$rows = $stmt->fetchAll();
function report_duration(int $seconds): string { return sprintf('%dh %02dm', intdiv($seconds, 3600), intdiv($seconds % 3600, 60)); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Client Contribution Report</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/saas.css"></head><body class="bg-light"><main class="container py-4"><div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">Client Contribution Report</h1><p class="text-muted mb-0"><?= $isAdmin ? 'All employees combined' : 'Only your assigned clients' ?></p></div><a href="<?= $isAdmin ? 'admin_dashboard.php' : 'employee_dashboard.php' ?>">Dashboard</a></div><div class="card card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Client</th><th>Content uploaded</th><th>Approval rate</th><th>Total time</th><th>Tasks completed</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">No contribution data yet.</td></tr><?php else: foreach ($rows as $row): ?><tr><td><strong><?= e($row['name']) ?></strong><br><small class="text-muted"><?= e($row['industry'] ?? '') ?></small></td><td><?= (int) $row['uploads'] ?></td><td><?= number_format((float) $row['approval_rate'], 1) ?>%</td><td><?= e(report_duration((int) $row['time_spent'])) ?></td><td><?= (int) $row['tasks_completed'] ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div></main></body></html>
