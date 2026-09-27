<?php
declare(strict_types=1);

require_once __DIR__ . '/access.php';
require_login();
$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
if (!$isAdmin && ($_SESSION['user']['role'] ?? '') !== 'employee') { http_response_code(403); exit('Forbidden'); }
if (!$isAdmin) { require_permission('manage_plans'); }
if ($isAdmin) { require_once __DIR__ . '/finance.php'; } else { require_once __DIR__ . '/employee_ui.php'; }

$pdo = db();
$clientId = (int) ($_GET['client_id'] ?? $_POST['client_id'] ?? 0);
$access = $isAdmin ? $pdo->prepare('SELECT id, name, industry FROM clients WHERE id = ? AND active = 1') : $pdo->prepare('SELECT c.id, c.name, c.industry FROM clients c INNER JOIN client_employees ce ON ce.client_id = c.id WHERE c.id = ? AND ce.employee_id = ? AND c.active = 1');
$access->execute($isAdmin ? [$clientId] : [$clientId, (int) $_SESSION['user']['id']]);
$client = $access->fetch();
if (!$client) { http_response_code(403); exit('You do not have access to this client.'); }

$message = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $start = (string) ($_POST['period_start'] ?? '');
        $end = (string) ($_POST['period_end'] ?? '');
        $title = trim((string) ($_POST['title'] ?? ''));
        $assignedTo = $isAdmin ? (int) ($_POST['assigned_to'] ?? 0) : (int) $_SESSION['user']['id'];
        if (!$start || !$end || $start > $end || $title === '' || !$assignedTo) { throw new RuntimeException('Enter a valid period, title and assignee.'); }
        $validEmployee = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'employee' AND active = 1");
        $validEmployee->execute([$assignedTo]);
        if (!$validEmployee->fetchColumn()) { throw new RuntimeException('Select a valid employee.'); }
        $goal = trim((string) ($_POST['goal'] ?? ''));
        $targets = trim((string) ($_POST['content_targets'] ?? ''));
        $pdo->beginTransaction();
        $plannedDate = (string) ($_POST['planned_date'] ?? $start);
        $contentType = in_array($_POST['content_type'] ?? '', ['reel', 'static', 'carousel'], true) ? $_POST['content_type'] : null;
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $pdo->prepare('INSERT INTO content_plans (client_id, created_by, plan_type, period_start, period_end, title, planned_date, content_type, notes, goal, content_targets, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$clientId, (int) $_SESSION['user']['id'], $_POST['plan_type'] ?? 'Monthly', $start, $end, $title, $plannedDate ?: $start, $contentType, $notes, $goal, $targets, $_POST['status'] ?? 'Planned']);
        $taskDescription = trim('Goal: ' . $goal . "\nTargets: " . $targets);
        $pdo->prepare('INSERT INTO tasks (client_id, assigned_to, assigned_by, title, description, due_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$clientId, $assignedTo, (int) $_SESSION['user']['id'], 'Plan: ' . $title, $taskDescription, $end, 'pending']);
        $pdo->commit();
        $message = 'Plan saved and task created automatically.';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = $exception->getMessage();
    }
}

$assignees = $pdo->query("SELECT id, name FROM users WHERE role = 'employee' AND active = 1 ORDER BY name")->fetchAll();
$plansStmt = $pdo->prepare('SELECT * FROM content_plans WHERE client_id = ? ORDER BY period_start DESC, id DESC');
$plansStmt->execute([$clientId]);
$plans = $plansStmt->fetchAll();
$view = ($_GET['view'] ?? 'month') === 'week' ? 'week' : 'month';
$anchor = (string) ($_GET[$view === 'week' ? 'week' : 'month'] ?? date($view === 'week' ? 'Y-m-d' : 'Y-m'));
$anchorDate = $view === 'week' ? new DateTimeImmutable($anchor) : new DateTimeImmutable($anchor . '-01');
$calendarStart = $view === 'week' ? $anchorDate->modify('monday this week') : $anchorDate->modify('first day of this month')->modify('monday this week');
$calendarDays = $view === 'week' ? 7 : 42;
function plan_on_day(array $plan, string $day): bool { return $plan['period_start'] <= $day && $plan['period_end'] >= $day; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Client Plans</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>.plan-calendar{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:1px;background:#e2e8f0;border:1px solid #e2e8f0}.plan-day{background:#fff;min-height:110px;padding:8px}.plan-day.muted{background:#f8fafc;color:#94a3b8}.plan-chip{display:block;background:#eef2ff;color:#4338ca;border-radius:6px;padding:4px 6px;margin-top:5px;font-size:11px;line-height:1.25}.plan-day strong{font-size:12px}</style></head><body class="bg-light"><main class="container py-4"><div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1"><?=e($client['name'])?> Plans</h1><div class="text-muted"><?=e($client['industry'] ?? '')?> · Monthly and weekly content planning</div></div><a class="btn btn-outline-primary" href="<?=$isAdmin ? 'admin_dashboard.php' : 'employee_clients.php'?>">Back</a></div><?php if ($message): ?><div class="alert alert-success"><?=e($message)?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?><form method="post" class="card card-body mb-4"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="client_id" value="<?=$clientId?>"><div class="row g-3"><div class="col-md-2"><label class="form-label">Plan type</label><select class="form-select" name="plan_type"><option>Monthly</option><option>Weekly</option></select></div><div class="col-md-2"><label class="form-label">Start</label><input class="form-control" type="date" name="period_start" required></div><div class="col-md-2"><label class="form-label">End</label><input class="form-control" type="date" name="period_end" required></div><div class="col-md-4"><label class="form-label">Plan title</label><input class="form-control" name="title" placeholder="September awareness plan" required></div><div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><option>Planned</option><option>In Progress</option><option>Completed</option></select></div><?php if ($isAdmin): ?><div class="col-md-3"><label class="form-label">Assign to</label><select class="form-select" name="assigned_to" required><option value="">Select employee</option><?php foreach ($assignees as $assignee): ?><option value="<?=$assignee['id']?>"><?=e($assignee['name'])?></option><?php endforeach; ?></select></div><?php endif; ?><div class="col-12"><label class="form-label">Goal</label><textarea class="form-control" name="goal" rows="2" placeholder="What should this plan achieve?"></textarea></div><div class="col-12"><label class="form-label">Content targets</label><textarea class="form-control" name="content_targets" rows="2" placeholder="8 reels, 4 carousels, 8 static posts"></textarea></div><div class="col-12"><button class="btn btn-primary">Save plan and create task</button></div></div></form><div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">Plan calendar</h2><div><a class="btn btn-sm <?= $view === 'month' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?client_id=<?=$clientId?>&view=month&month=<?=e($view === 'month' ? $anchor : date('Y-m'))?>">Month</a> <a class="btn btn-sm <?= $view === 'week' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?client_id=<?=$clientId?>&view=week&week=<?=e($view === 'week' ? $anchor : date('Y-m-d'))?>">Week</a></div></div><div class="plan-calendar mb-4"><?php for ($i = 0; $i < $calendarDays; $i++): $day = $calendarStart->modify('+' . $i . ' days'); $dayString = $day->format('Y-m-d'); $muted = $view === 'month' && $day->format('m') !== $anchorDate->format('m'); ?><div class="plan-day<?= $muted ? ' muted' : '' ?>"><strong><?=e($day->format('d M'))?></strong><?php foreach ($plans as $plan): if (plan_on_day($plan, $dayString)): ?><span class="plan-chip"><?=e($plan['plan_type'])?>: <?=e($plan['title'])?></span><?php endif; endforeach; ?></div><?php endfor; ?></div><div class="card card-body"><h2 class="h5">Saved plans</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Type</th><th>Period</th><th>Title</th><th>Targets</th><th>Status</th></tr></thead><tbody><?php if (!$plans): ?><tr><td colspan="5" class="text-center text-muted py-4">No plans created yet.</td></tr><?php else: foreach ($plans as $plan): ?><tr><td><?=e($plan['plan_type'])?></td><td><?=e($plan['period_start'] . ' to ' . $plan['period_end'])?></td><td><strong><?=e($plan['title'])?></strong><br><small><?=e($plan['goal'] ?? '')?></small></td><td><?=e($plan['content_targets'] ?? '')?></td><td><?=e($plan['status'])?></td></tr><?php endforeach; endif; ?></tbody></table></div></div></main></body></html>
