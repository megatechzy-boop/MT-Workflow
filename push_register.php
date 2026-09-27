<?php
declare(strict_types=1);
require_once __DIR__ . '/access.php';
header('Content-Type: application/json; charset=utf-8');
if (!is_logged_in()) { echo json_encode(['authenticated' => false]); exit; }
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($data['csrf_token'] ?? ''))) { http_response_code(419); echo json_encode(['error' => 'Invalid form token.']); exit; }
    $subscriptionId = trim((string) ($data['subscription_id'] ?? ''));
    if ($subscriptionId === '' || strlen($subscriptionId) > 255) { http_response_code(422); echo json_encode(['error' => 'Invalid subscription.']); exit; }
    $pdo->prepare('INSERT INTO push_subscriptions (user_id, onesignal_player_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), updated_at=CURRENT_TIMESTAMP')->execute([(int) $_SESSION['user']['id'], $subscriptionId]);
}
echo json_encode(['authenticated' => true, 'user_id' => (int) $_SESSION['user']['id'], 'csrf_token' => csrf_token()]);
