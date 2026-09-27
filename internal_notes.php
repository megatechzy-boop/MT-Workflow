<?php
declare(strict_types=1);
require_once __DIR__ . '/access.php';
require_login();
$pdo = db();
$user = $_SESSION['user'];
$taskId = (int) ($_GET['task_id'] ?? $_POST['task_id'] ?? 0);
$taskStmt = $pdo->prepare('SELECT t.id, t.title, t.client_id, c.name AS client_name, t.assigned_to FROM tasks t INNER JOIN clients c ON c.id = t.client_id WHERE t.id = ?');
$taskStmt->execute([$taskId]);
$task = $taskStmt->fetch();
if (!$task || ($user['role'] !== 'admin' && (int) $task['assigned_to'] !== (int) $user['id'])) {
    http_response_code(403);
    exit('Internal notes are available only to the assigned employee and admin.');
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $message = trim((string) ($_POST['message'] ?? ''));
    if ($message === '') {
        $error = 'Write a note before saving.';
    } else {
        $pdo->prepare('INSERT INTO internal_notes (task_id, client_id, employee_id, message) VALUES (?, ?, ?, ?)')->execute([$taskId, $task['client_id'], $user['id'], $message]);
        header('Location: internal_notes.php?task_id=' . $taskId);
        exit;
    }
}
$notesStmt = $pdo->prepare('SELECT n.message, n.created_at, u.name FROM internal_notes n INNER JOIN users u ON u.id = n.employee_id WHERE n.task_id = ? ORDER BY n.created_at');
$notesStmt->execute([$taskId]);
$notes = $notesStmt->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Internal Notes</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/saas.css"></head><body class="bg-light"><main class="container py-4" style="max-width:850px"><div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">Internal Notes</h1><p class="text-muted mb-0"><?= e($task['title']) ?> · <?= e($task['client_name']) ?></p></div><a href="<?= $user['role'] === 'admin' ? 'assign_task.php' : 'my_tasks.php' ?>">Back</a></div><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><div class="card card-body mb-3"><h2 class="h5">Team-only thread</h2><?php if (!$notes): ?><div class="text-muted">No internal notes yet.</div><?php else: foreach ($notes as $note): ?><div class="border-bottom py-2"><strong><?= e($note['name']) ?></strong> <small class="text-muted"><?= e($note['created_at']) ?></small><div><?= nl2br(e($note['message'])) ?></div></div><?php endforeach; endif; ?></div><form method="post" class="card card-body"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="task_id" value="<?= $taskId ?>"><label class="form-label">Add internal note</label><textarea class="form-control mb-3" name="message" rows="3" required></textarea><button class="btn btn-primary">Save note</button></form></main></body></html>
