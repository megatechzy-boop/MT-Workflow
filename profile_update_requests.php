<?php
declare(strict_types=1);
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/notifications.php';
require_login();
$pdo = db(); $role = $_SESSION['user']['role'] ?? '';
if ($role === 'client') {
    $stmt = $pdo->prepare('SELECT id, name FROM clients WHERE user_id = ? AND active = 1 LIMIT 1'); $stmt->execute([$_SESSION['user']['id']]); $client = $stmt->fetch();
    if (!$client) { http_response_code(403); exit('Your login is not linked to a client.'); }
    header('Location: client_intake_form.php?client_id=' . (int) $client['id']);
    exit;
    $message = null; $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf(); $changes = trim((string) ($_POST['requested_changes'] ?? ''));
        if ($changes === '') { $error = 'Describe the changes you need.'; }
        else {
            $pdo->prepare('INSERT INTO profile_update_requests (client_id, requested_by, requested_changes) VALUES (?, ?, ?)')->execute([$client['id'], $_SESSION['user']['id'], $changes]);
            $admins = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND active = 1 AND email <> ''")->fetchAll();
            foreach ($admins as $admin) { send_notification((string) $admin['email'], 'Client profile update request', $client['name'] . ' requested a profile update: ' . $changes); }
            $message = 'Your update request was sent to the admin.';
        }
    }
    $stmt = $pdo->prepare('SELECT requested_changes, status, created_at FROM profile_update_requests WHERE client_id = ? ORDER BY created_at DESC'); $stmt->execute([$client['id']]); $requests = $stmt->fetchAll();
    ?>
    <!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Request Profile Update</title><link rel="stylesheet" href="assets/css/saas.css"><script src="assets/js/session-timeout.js" defer></script></head><body class="saas-body"><main class="container py-4" style="max-width:800px"><div class="d-flex justify-content-between"><h1 class="h3">Request Profile Update</h1><a href="client_dashboard.php">Dashboard</a></div><?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post" class="card card-body mb-4"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="form-label">What should be changed?</label><textarea class="form-control mb-3" name="requested_changes" rows="5" required></textarea><button class="btn btn-primary">Send request</button></form><div class="card card-body"><h2 class="h5">Previous requests</h2><?php foreach ($requests as $request): ?><div class="border-bottom py-2"><?= nl2br(e($request['requested_changes'])) ?><br><span class="badge text-bg-secondary"><?= e($request['status']) ?></span> <small class="text-muted"><?= e($request['created_at']) ?></small></div><?php endforeach; ?></div></main></body></html>
    <?php exit;
}
require_role('admin');
require_once __DIR__ . '/finance.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $id = (int) ($_POST['id'] ?? 0); $status = (string) ($_POST['status'] ?? '');
    if (in_array($status, ['Pending', 'Reviewed', 'Completed'], true)) { $pdo->prepare('UPDATE profile_update_requests SET status = ? WHERE id = ?')->execute([$status, $id]); }
}
$requests = $pdo->query('SELECT r.*, c.name client_name, u.email client_email FROM profile_update_requests r INNER JOIN clients c ON c.id = r.client_id INNER JOIN users u ON u.id = r.requested_by ORDER BY r.created_at DESC')->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Profile Update Requests</title><link rel="stylesheet" href="assets/css/saas.css"></head><body class="bg-light"><main class="container py-4"><div class="d-flex justify-content-between"><h1 class="h3">Profile Update Requests</h1><a href="admin_dashboard.php">Dashboard</a></div><div class="card card-body"><div class="table-responsive"><table class="table"><thead><tr><th>Client</th><th>Request</th><th>Date</th><th>Status</th></tr></thead><tbody><?php foreach ($requests as $request): ?><tr><td><?= e($request['client_name']) ?></td><td><?= nl2br(e($request['requested_changes'])) ?></td><td><?= e($request['created_at']) ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $request['id'] ?>"><select class="form-select form-select-sm" name="status" onchange="this.form.submit()"><?php foreach (['Pending', 'Reviewed', 'Completed'] as $status): ?><option <?= $request['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></form></td></tr><?php endforeach; ?></tbody></table></div></div></main></body></html>
