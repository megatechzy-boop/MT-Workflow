<?php
declare(strict_types=1);
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/notifications.php';
$clientId = (int) ($_GET['client_id'] ?? $_POST['client_id'] ?? 0);
require_client_access($clientId);
$role = $_SESSION['user']['role'] ?? '';
if ($role === 'employee') { http_response_code(403); exit('Employees can view this profile only.'); }
$fields = ['business_goals'=>'Business goals','business_stage'=>'Business stage','target_audience'=>'Target audience','customer_journey'=>'Customer journey','sales_capacity'=>'Sales capacity','brand_positioning'=>'Brand positioning','content_preferences'=>'Content preferences','marketing_history'=>'Marketing history','competitor_positioning'=>'Competitor positioning','business_operations'=>'Business operations','success_metrics'=>'Success metrics','project_requirement'=>'Project requirement'];
$message = null; $error = null; $pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO client_details (client_id, field_name, field_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)');
        foreach ($fields as $name => $label) { $stmt->execute([$clientId, $name, trim((string) ($_POST[$name] ?? ''))]); }
        if (!empty($_FILES['assets']['name'][0])) {
            $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','video/mp4'=>'mp4','application/pdf'=>'pdf'];
            $uploadDir = __DIR__ . '/uploads/clients/' . $clientId; if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
            foreach ($_FILES['assets']['tmp_name'] as $index => $tmpName) {
                if ($_FILES['assets']['error'][$index] !== UPLOAD_ERR_OK || !is_uploaded_file($tmpName)) { continue; }
                if ($_FILES['assets']['size'][$index] > 20 * 1024 * 1024) { throw new RuntimeException('Each asset must be 20 MB or smaller.'); }
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
                if (!isset($allowed[$mime])) { throw new RuntimeException('One selected asset type is not allowed.'); }
                $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
                if (!move_uploaded_file($tmpName, $uploadDir . '/' . $filename)) { throw new RuntimeException('Could not store the uploaded asset.'); }
                $type = str_starts_with($mime, 'image/') ? 'photo' : (str_starts_with($mime, 'video/') ? 'video' : 'brochure');
                $pdo->prepare('INSERT INTO assets (client_id, type, file_path) VALUES (?, ?, ?)')->execute([$clientId, $type, 'uploads/clients/' . $clientId . '/' . $filename]);
            }
        }
        $pdo->commit();
        if ($role === 'client') {
            $notify = $pdo->prepare('SELECT u.email FROM users u INNER JOIN client_employees ce ON ce.employee_id = u.id WHERE ce.client_id = ? AND u.active = 1'); $notify->execute([$clientId]);
            foreach ($notify->fetchAll() as $employee) { send_notification((string) $employee['email'], 'Client profile updated', 'The client brief was updated. Please review the latest profile details.'); }
        }
        $message = 'Profile saved successfully.';
    } catch (Throwable $exception) { if ($pdo->inTransaction()) { $pdo->rollBack(); } $error = $exception->getMessage(); }
}
$stmt = $pdo->prepare('SELECT name, industry FROM clients WHERE id = ? AND active = 1'); $stmt->execute([$clientId]); $client = $stmt->fetch();
if (!$client) { http_response_code(404); exit('Client not found.'); }
$stmt = $pdo->prepare('SELECT field_name, field_value FROM client_details WHERE client_id = ?'); $stmt->execute([$clientId]); $values = []; foreach ($stmt->fetchAll() as $row) { $values[$row['field_name']] = $row['field_value']; }
echo '<style>.row{display:flex;flex-wrap:wrap;margin:-7px}.row>*{padding:7px}.col-lg-6{width:50%}@media(max-width:900px){.col-lg-6{width:100%}}.profile-input{font:inherit;font-size:14px}</style>';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Edit Profile</title><link rel="stylesheet" href="assets/css/saas.css"><style>.edit-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 4px 20px rgba(15,23,42,.05)}.field-label{font-size:12px;text-transform:uppercase;letter-spacing:.04em;font-weight:700;color:#64748b}.profile-input{border:1px solid #dbe2ea;border-radius:10px;padding:11px 13px;width:100%;color:#1e293b;background:#fff}.profile-input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.15);outline:0}.field-box{background:#f8fafc;border:1px solid #eef2f7;border-radius:12px;padding:14px}</style><script src="assets/js/session-timeout.js" defer></script></head><body class="saas-body"><div class="saas-shell"><aside class="saas-sidebar"><div class="brand"><span>MT</span> Mega Techzy</div><div class="nav-label">Workspace</div><a class="saas-nav" href="client_dashboard.php">▦ <span>Overview</span></a><a class="saas-nav" href="client_content_plan.php">◷ <span>Content plan</span></a><a class="saas-nav" href="content_archive.php">▤ <span>Archive</span></a><a class="saas-nav active" href="view_client_profile.php?client_id=<?= $clientId ?>">♙ <span>My profile</span></a><a class="saas-nav" href="my_invoices.php">▧ <span>Invoices</span></a><div class="nav-label">Account</div><a class="saas-nav" href="logout.php">↪ <span>Log out</span></a></aside><main class="saas-main"><header class="saas-topbar mb-4"><div><h1 class="saas-title">Edit profile</h1><div class="saas-subtitle"><?= e($client['name']) ?> · <?= e($client['industry'] ?? '') ?></div></div><a class="btn-outline-indigo" href="view_client_profile.php?client_id=<?= $clientId ?>">View profile</a></header><?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="client_id" value="<?= $clientId ?>"><section class="edit-card mb-4"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h4 mb-1">Business information</h2><p class="text-muted mb-0">Keep your profile details up to date.</p></div><span class="badge rounded-pill text-bg-light"><?= count($fields) ?> fields</span></div><div class="row g-3"><?php foreach ($fields as $name => $label): ?><div class="col-lg-6"><div class="field-box"><label class="field-label d-block mb-2" for="<?= e($name) ?>"><?= e($label) ?></label><?php if ($name === 'business_stage'): ?><select class="profile-input" id="<?= e($name) ?>" name="<?= e($name) ?>"><option value="">Select stage</option><?php foreach (['new','established','expansion'] as $stage): ?><option value="<?= $stage ?>" <?= (($values[$name] ?? '') === $stage) ? 'selected' : '' ?>><?= ucfirst($stage) ?></option><?php endforeach; ?></select><?php else: ?><textarea class="profile-input" id="<?= e($name) ?>" name="<?= e($name) ?>" rows="4"><?= e($values[$name] ?? '') ?></textarea><?php endif; ?></div></div><?php endforeach; ?></div></section><section class="edit-card mb-4"><h2 class="h4 mb-1">Brand assets</h2><p class="text-muted">Upload logos, photos, videos or brochures. Maximum 20 MB per file.</p><input class="profile-input" type="file" name="assets[]" multiple accept=".jpg,.jpeg,.png,.webp,.mp4,.pdf"></section><div class="d-flex justify-content-end gap-2"><a class="btn-outline-indigo" href="view_client_profile.php?client_id=<?= $clientId ?>">Cancel</a><button class="btn-indigo" type="submit">Save profile</button></div></form></main></div></body></html>
