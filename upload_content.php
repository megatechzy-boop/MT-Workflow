<?php
declare(strict_types=1);
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/notifications.php';
require_role('employee');
require_permission('upload_content');
require_once __DIR__ . '/employee_ui.php';

$pdo = db(); $message = null; $error = null;
$resubmitId = (int) ($_GET['resubmit_content_id'] ?? $_POST['resubmit_content_id'] ?? 0);
$resubmit = null;
if ($resubmitId > 0) {
    $stmt = $pdo->prepare("SELECT c.* FROM content c INNER JOIN client_employees ce ON ce.client_id = c.client_id WHERE c.id = ? AND c.employee_id = ? AND ce.employee_id = ? AND c.status = 'changes_requested'");
    $stmt->execute([$resubmitId, $_SESSION['user']['id'], $_SESSION['user']['id']]); $resubmit = $stmt->fetch();
    if (!$resubmit) { http_response_code(403); exit('This content is not available for re-upload.'); }
}
$clientId = (int) ($_GET['client_id'] ?? $_POST['client_id'] ?? ($resubmit['client_id'] ?? 0));
if ($clientId > 0) { require_client_access($clientId); }
$stmt = $pdo->prepare("SELECT c.id, c.name FROM clients c INNER JOIN client_employees ce ON ce.client_id = c.id WHERE ce.employee_id = ? AND c.active = 1 ORDER BY c.name");
$stmt->execute([$_SESSION['user']['id']]); $clients = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $reuploadId = (int) ($_POST['resubmit_content_id'] ?? 0);
        $clientIds = $reuploadId > 0 ? [(int) ($_POST['client_id'] ?? 0)] : array_values(array_unique(array_filter(array_map('intval', $_POST['client_ids'] ?? []))));
        if (!$clientIds) { $clientIds = [(int) ($_POST['client_id'] ?? 0)]; }
        if (!$clientIds || in_array(0, $clientIds, true)) { throw new RuntimeException('Select at least one client.'); }
        foreach ($clientIds as $selectedClientId) { require_client_access($selectedClientId); }
        $title = trim((string) ($_POST['title'] ?? '')); $type = (string) ($_POST['content_type'] ?? ''); $caption = trim((string) ($_POST['caption'] ?? '')); $link = trim((string) ($_POST['external_link'] ?? ''));
        if ($title === '' || !in_array($type, ['video', 'static', 'carousel'], true)) { throw new RuntimeException('Title and content type are required.'); }
        if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) { throw new RuntimeException('Enter a valid external link.'); }
        $files = $_FILES['files'] ?? null; $names = $files ? array_filter($files['name'] ?? []) : [];
        if ($link === '' && !$names) { throw new RuntimeException('Upload a new file or provide an external link.'); }
        if ($link !== '' && $names) { throw new RuntimeException('Use either uploaded files or an external link, not both.'); }
        if ($type === 'carousel' && count($names) > 10) { throw new RuntimeException('A carousel can contain up to 10 images.'); }
        if ($type !== 'carousel' && count($names) > 1) { throw new RuntimeException('This content type accepts only one file.'); }
        $safeExtensions = ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $allowed = $type === 'video' ? ['video/mp4', 'video/quicktime'] : ['image/jpeg', 'image/png', 'image/webp'];
        $pdo->beginTransaction(); $createdIds = [];
        foreach ($clientIds as $selectedClientId) {
            if ($reuploadId > 0) {
                $check = $pdo->prepare("SELECT c.* FROM content c INNER JOIN client_employees ce ON ce.client_id = c.client_id WHERE c.id = ? AND c.employee_id = ? AND ce.employee_id = ? AND c.status = 'changes_requested'");
                $check->execute([$reuploadId, $_SESSION['user']['id'], $_SESSION['user']['id']]); $existing = $check->fetch();
                if (!$existing || (int) $existing['client_id'] !== $selectedClientId) { throw new RuntimeException('This content cannot be re-uploaded.'); }
                $contentId = $reuploadId; $versionStmt = $pdo->prepare('SELECT COALESCE(MAX(version), 0) + 1 FROM content_files WHERE content_id = ?'); $versionStmt->execute([$contentId]); $version = (int) $versionStmt->fetchColumn();
                $pdo->prepare("UPDATE content SET title = ?, caption = ?, status = 'pending', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$title, $caption ?: null, $contentId]);
                $pdo->prepare("INSERT INTO content_status_history (content_id, user_id, old_status, new_status, note) VALUES (?, ?, 'changes_requested', 'pending', ?)")->execute([$contentId, $_SESSION['user']['id'], 'Version ' . $version . ' re-uploaded']);
            } else {
                $pdo->prepare('INSERT INTO content (client_id, employee_id, title, content_type, caption) VALUES (?, ?, ?, ?, ?)')->execute([$selectedClientId, $_SESSION['user']['id'], $title, $type, $caption ?: null]); $contentId = (int) $pdo->lastInsertId(); $version = 1;
                $pdo->prepare("INSERT INTO content_status_history (content_id, user_id, old_status, new_status, note) VALUES (?, ?, NULL, 'pending', 'Content submitted')")->execute([$contentId, $_SESSION['user']['id']]);
            }
            $dir = __DIR__ . '/uploads/' . $selectedClientId . '/' . $contentId; if ($names && !is_dir($dir)) { mkdir($dir, 0755, true); }
            $stored = $pdo->prepare('INSERT INTO content_files (content_id, file_path, sort_order, version) VALUES (?, ?, ?, ?)');
            if ($link !== '') { $stored->execute([$contentId, $link, 0, $version]); }
            else { foreach ($names as $index => $originalName) { $tmpName = $files['tmp_name'][$index]; if ($files['error'][$index] !== UPLOAD_ERR_OK || !is_uploaded_file($tmpName)) { throw new RuntimeException('A file upload failed.'); } $limit = $type === 'video' ? 100 * 1024 * 1024 : 5 * 1024 * 1024; if ($files['size'][$index] > $limit) { throw new RuntimeException('A selected file exceeds the size limit.'); } $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName); if (!isset($safeExtensions[$mime]) || !in_array($mime, $allowed, true)) { throw new RuntimeException('A selected file type is not allowed.'); } $filename = bin2hex(random_bytes(12)) . '.' . $safeExtensions[$mime]; if (!copy($tmpName, $dir . '/' . $filename)) { throw new RuntimeException('Could not store the uploaded file.'); } $stored->execute([$contentId, 'uploads/' . $selectedClientId . '/' . $contentId . '/' . $filename, $index, $version]); } }
            $createdIds[] = $contentId; $notify = $pdo->prepare('SELECT u.email FROM users u INNER JOIN clients c ON c.user_id = u.id WHERE c.id = ?'); $notify->execute([$selectedClientId]); if ($clientEmail = $notify->fetchColumn()) { send_notification((string) $clientEmail, 'Content is ready for approval', 'Content has been submitted for your review: ' . $title); }
        }
        $pdo->commit();
        foreach ($createdIds as $createdId) { log_activity((int) $_SESSION['user']['id'], $reuploadId > 0 ? 'Re-uploaded content version' : 'Uploaded content', 'content', $createdId); }
        $message = $reuploadId > 0 ? 'New version uploaded for approval.' : count($clientIds) . ' client content item(s) submitted for approval.';
        $resubmit = null; $resubmitId = 0;
    } catch (Throwable $exception) { if ($pdo->inTransaction()) { $pdo->rollBack(); } $error = $exception->getMessage(); }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $resubmit ? 'Re-upload Content' : 'Upload Content' ?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/saas.css"></head><body class="bg-light"><main class="container py-4" style="max-width:850px"><div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3"><?= $resubmit ? 'Re-upload Content' : 'Upload Content' ?></h1><a href="employee_dashboard.php">Dashboard</a></div><?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><?php if ($resubmit): ?><div class="alert alert-warning">Client requested changes. Upload a new version; the previous version remains in history.</div><?php endif; ?><form method="post" enctype="multipart/form-data" class="card card-body"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="resubmit_content_id" value="<?= $resubmitId ?>"><div class="mb-3"><label class="form-label">Client(s)</label><?php if ($resubmit): ?><input type="hidden" name="client_id" value="<?= $clientId ?>"><div class="form-control bg-light"><?= e($resubmit['client_id']) ?> — re-uploading the existing client content</div><?php else: ?><div class="row g-2"><?php foreach ($clients as $client): ?><div class="col-md-6"><label class="border rounded p-2 w-100"><input type="checkbox" name="client_ids[]" value="<?= (int) $client['id'] ?>"> <?= e($client['name']) ?></label></div><?php endforeach; ?></div><?php endif; ?></div><div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" value="<?= e($resubmit['title'] ?? '') ?>" required></div><div class="mb-3"><label class="form-label">Content type</label><select class="form-select" name="content_type" required><option value="video" <?= ($resubmit['content_type'] ?? '') === 'video' ? 'selected' : '' ?>>Reel / Video</option><option value="static" <?= ($resubmit['content_type'] ?? '') === 'static' ? 'selected' : '' ?>>Static image</option><option value="carousel" <?= ($resubmit['content_type'] ?? '') === 'carousel' ? 'selected' : '' ?>>Carousel</option></select></div><div class="mb-3"><label class="form-label">New file(s)</label><input class="form-control" type="file" name="files[]" multiple accept=".mp4,.mov,.jpg,.jpeg,.png,.webp"></div><div class="mb-3"><label class="form-label">Or external link</label><input class="form-control" type="url" name="external_link"></div><div class="mb-3"><label class="form-label">Caption / copy</label><textarea class="form-control" name="caption" rows="4"><?= e($resubmit['caption'] ?? '') ?></textarea></div><button class="btn btn-primary" type="submit"><?= $resubmit ? 'Upload new version' : 'Submit for approval' ?></button></form></main></body></html>
