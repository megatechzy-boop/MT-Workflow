<?php
declare(strict_types=1);

require_once __DIR__ . '/access.php';
require_role('employee');
require_once __DIR__ . '/employee_ui.php';

$stmt = db()->prepare('SELECT c.id, c.name, c.industry, COUNT(DISTINCT content.id) AS content_count FROM clients c INNER JOIN client_employees ce ON ce.client_id = c.id LEFT JOIN content ON content.client_id = c.id WHERE ce.employee_id = ? AND c.active = 1 GROUP BY c.id, c.name, c.industry ORDER BY c.name');
$stmt->execute([(int) $_SESSION['user']['id']]);
$clients = $stmt->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Client Profiles</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-4"><div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">Client Profiles</h1><div class="text-muted">View your assigned clients and start content work.</div></div><a class="btn btn-primary" href="employee_dashboard.php">Back to overview</a></div><div class="card card-body"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Client</th><th>Industry</th><th>Content items</th><th>Actions</th></tr></thead><tbody><?php if (!$clients): ?><tr><td colspan="4" class="text-center text-muted py-5">No clients assigned yet.</td></tr><?php else: foreach ($clients as $client): ?><tr><td><strong><?= e($client['name']) ?></strong></td><td><?= e($client['industry'] ?? '') ?></td><td><?= (int) $client['content_count'] ?></td><td><a class="btn btn-outline-primary btn-sm" href="view_client_profile.php?client_id=<?= (int) $client['id'] ?>">Profile</a> <a class="btn btn-primary btn-sm" href="upload_content.php?client_id=<?= (int) $client['id'] ?>">Upload</a> <a class="btn btn-outline-primary btn-sm" href="employee_plans.php?client_id=<?= (int) $client['id'] ?>">Plan</a></td></tr><?php endforeach; endif; ?></tbody></table></div></div></main></body></html>
