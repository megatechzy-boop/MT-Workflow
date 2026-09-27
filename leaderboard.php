<?php
declare(strict_types=1);
require_once __DIR__ . '/access.php';
require_login();
if (!in_array($_SESSION['user']['role'] ?? '', ['admin', 'employee'], true)) { http_response_code(403); exit('Forbidden'); }
$isAdmin = $_SESSION['user']['role'] === 'admin';
if ($isAdmin) { require_once __DIR__ . '/finance.php'; } else { require_once __DIR__ . '/employee_ui.php'; }
$month = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['month'] ?? '')) ? (string) $_GET['month'] : date('Y-m');
$from = $month . '-01'; $to = date('Y-m-d', strtotime($from . ' +1 month'));
$employees = db()->query("SELECT id, name FROM users WHERE role = 'employee' AND active = 1 ORDER BY name")->fetchAll();
$rows = [];
foreach ($employees as $employee) {
    $id = (int) $employee['id'];
    $q = db()->prepare("SELECT SUM(status = 'approved') approved, SUM(status = 'changes_requested') changes_requested FROM content WHERE employee_id = ? AND created_at >= ? AND created_at < ?");
    $q->execute([$id, $from, $to]); $content = $q->fetch() ?: [];
    $reviewed = (int) ($content['approved'] ?? 0) + (int) ($content['changes_requested'] ?? 0);
    $q = db()->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'completed' AND created_at >= ? AND created_at < ?");
    $q->execute([$id, $from, $to]); $completed = (int) $q->fetchColumn();
    $q = db()->prepare("SELECT COUNT(*) FROM content c WHERE c.employee_id = ? AND c.created_at >= ? AND c.created_at < ? AND c.status = 'approved' AND NOT EXISTS (SELECT 1 FROM content_status_history h WHERE h.content_id = c.id AND h.new_status = 'changes_requested')");
    $q->execute([$id, $from, $to]); $zeroRevision = (int) $q->fetchColumn();
    $rows[] = ['name' => $employee['name'], 'approval_rate' => $reviewed ? round(((int) ($content['approved'] ?? 0) / $reviewed) * 100, 1) : 0, 'completed' => $completed, 'zero_revision' => $zeroRevision];
}
usort($rows, fn(array $a, array $b): int => [$b['approval_rate'], $b['completed'], $b['zero_revision']] <=> [$a['approval_rate'], $a['completed'], $a['zero_revision']]);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Performance Leaderboard</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/saas.css"></head><body class="bg-light"><main class="container py-4"><div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">Performance Leaderboard</h1><p class="text-muted mb-0">Monthly ranking · zero-revision score resets each month</p></div><a href="<?= $isAdmin ? 'admin_dashboard.php' : 'employee_dashboard.php' ?>">Dashboard</a></div><form class="mb-3"><label class="form-label">Month</label><input class="form-control" style="max-width:220px" type="month" name="month" value="<?= e($month) ?>"><button class="btn btn-primary mt-2">Refresh</button></form><div class="card card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Rank</th><th>Employee</th><th>Approval rate</th><th>Tasks completed</th><th>Zero-revision streak</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted">No employee data.</td></tr><?php else: foreach ($rows as $rank => $row): ?><tr><td><strong>#<?= $rank + 1 ?></strong></td><td><?= e($row['name']) ?></td><td><?= number_format($row['approval_rate'], 1) ?>%</td><td><?= $row['completed'] ?></td><td><?= $row['zero_revision'] ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div></main></body></html>
