<?php
declare(strict_types=1);
require_once __DIR__ . '/access.php';
require_login();
$isAdmin = $_SESSION['user']['role'] === 'admin';
$month = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['month'] ?? '')) ? (string) $_GET['month'] : date('Y-m');
$from = $month . '-01'; $to = date('Y-m-d', strtotime($from . ' +1 month'));
$stmt = $isAdmin ? db()->query("SELECT id, name FROM users WHERE role = 'employee' ORDER BY name") : db()->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'employee'");
if (!$isAdmin) { $stmt->execute([$_SESSION['user']['id']]); }
$employees = $stmt->fetchAll();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="performance-' . $month . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Employee', 'Attendance %', 'Tasks assigned', 'Tasks completed', 'Uploads', 'Approval rate %', 'Avg turnaround hours']);
foreach ($employees as $employee) {
    $id = (int) $employee['id'];
    $q = db()->prepare("SELECT COUNT(*) total, SUM(status = 'present') present FROM attendance WHERE employee_id = ? AND date >= ? AND date < ?"); $q->execute([$id, $from, $to]); $a = $q->fetch();
    $q = db()->prepare("SELECT COUNT(*) assigned, SUM(status = 'completed') completed FROM tasks WHERE assigned_to = ? AND created_at >= ? AND created_at < ?"); $q->execute([$id, $from, $to]); $t = $q->fetch();
    $q = db()->prepare("SELECT COUNT(*) uploads, SUM(status = 'approved') approved, SUM(status = 'changes_requested') changes_requested, AVG(CASE WHEN status = 'approved' THEN TIMESTAMPDIFF(HOUR, created_at, updated_at) END) turnaround FROM content WHERE employee_id = ? AND created_at >= ? AND created_at < ?"); $q->execute([$id, $from, $to]); $c = $q->fetch();
    $reviewed = (int) $c['approved'] + (int) $c['changes_requested'];
    fputcsv($out, [$employee['name'], $a['total'] ? round(((int) $a['present'] / (int) $a['total']) * 100, 1) : 0, (int) $t['assigned'], (int) $t['completed'], (int) $c['uploads'], $reviewed ? round(((int) $c['approved'] / $reviewed) * 100, 1) : 0, $c['turnaround'] !== null ? round((float) $c['turnaround'], 1) : 0]);
}
