<?php
declare(strict_types=1);
require_once __DIR__ . '/access.php';
require_role('employee');
$payload = json_decode(file_get_contents('php://input'), true) ?: [];
if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($payload['csrf_token'] ?? ''))) { http_response_code(419); exit(json_encode(['ok' => false])); }
$contentId = (int) ($payload['content_id'] ?? 0);
$fileIds = array_values(array_map('intval', $payload['file_ids'] ?? []));
$stmt = db()->prepare("SELECT client_id FROM content WHERE id = ? AND content_type = 'carousel'");
$stmt->execute([$contentId]);
$clientId = (int) $stmt->fetchColumn();
if (!$clientId || !client_is_visible_to_current_user($clientId)) { http_response_code(403); exit(json_encode(['ok' => false])); }
$stmt = db()->prepare('SELECT id FROM content_files WHERE content_id = ?');
$stmt->execute([$contentId]);
$existing = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
$check = $fileIds; sort($existing); sort($check);
if ($existing !== $check) { http_response_code(422); exit(json_encode(['ok' => false])); }
$update = db()->prepare('UPDATE content_files SET sort_order = ? WHERE id = ? AND content_id = ?');
foreach ($fileIds as $order => $fileId) { $update->execute([$order, $fileId, $contentId]); }
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
