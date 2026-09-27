<?php
declare(strict_types=1);
require_once __DIR__ . '/access.php';
require_role('client');
$pdo = db();
$stmt = $pdo->prepare('SELECT id, name FROM clients WHERE user_id = ? AND active = 1 LIMIT 1');
$stmt->execute([$_SESSION['user']['id']]); $client = $stmt->fetch();
$groups = [];
if ($client) {
    $stmt = $pdo->prepare("SELECT id, title, content_type, updated_at FROM content WHERE client_id = ? AND status = 'approved' ORDER BY updated_at DESC");
    $stmt->execute([$client['id']]);
    foreach ($stmt->fetchAll() as $item) { $groups[date('F Y', strtotime($item['updated_at']))][] = $item; }
}
echo '<style>.saas-topbar>a{display:inline-flex;align-items:center;border:1px solid #6366f1;border-radius:8px;padding:8px 13px;color:#6366f1;background:#fff;text-decoration:none;font-size:13px;font-weight:600}</style>';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Content Archive</title><link rel="stylesheet" href="assets/css/saas.css"><script src="assets/js/session-timeout.js" defer></script></head><body class="saas-body"><div class="saas-shell"><aside class="saas-sidebar"><div class="brand"><span>MT</span> Mega Techzy</div><div class="nav-label">Workspace</div><a class="saas-nav" href="client_dashboard.php">▦ <span>Overview</span></a><a class="saas-nav" href="client_content_plan.php">◷ <span>Content plan</span></a><a class="saas-nav active" href="content_archive.php">▤ <span>Archive</span></a><a class="saas-nav" href="view_client_profile.php?client_id=<?= $client['id'] ?>">♙ <span>My profile</span></a><a class="saas-nav" href="my_invoices.php">▧ <span>Invoices</span></a><div class="nav-label">Account</div><a class="saas-nav" href="logout.php">↪ <span>Log out</span></a></aside><main class="saas-main"><header class="saas-topbar"><div><h1 class="saas-title">Content archive</h1><div class="saas-subtitle">Previously approved content</div></div><a href="client_dashboard.php">Dashboard</a></header><?php if (!$client): ?><section class="saas-card"><div class="empty-state">Your login is not linked to a client profile.</div></section><?php elseif (!$groups): ?><section class="saas-card"><div class="empty-state"><span class="empty-icon">◌</span>No approved content yet.</div></section><?php else: foreach ($groups as $month => $items): ?><section class="saas-card" style="margin-bottom:16px"><h2><?= e($month) ?></h2><div style="overflow:auto"><table class="saas-table"><thead><tr><th>Title</th><th>Type</th><th>Approved content</th><th>Last updated</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><a href="approve_content.php?content_id=<?= $item['id'] ?>"><?= e($item['title']) ?></a></td><td><?= e(ucfirst($item['content_type'])) ?></td><td><span class="status-pill status-approved">Approved</span></td><td><?= e($item['updated_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endforeach; endif; ?></main></div></body></html>
