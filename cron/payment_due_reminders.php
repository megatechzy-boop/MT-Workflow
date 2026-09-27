<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/notifications.php';
$dryRun = in_array('--dry-run', $argv ?? [], true);
$stmt = db()->query("SELECT p.invoice_number, p.client_name, p.pending_amount, p.due_date, u.email FROM client_payments p INNER JOIN clients c ON c.id = p.client_id INNER JOIN users u ON u.id = c.user_id WHERE p.pending_amount > 0 AND p.due_date = DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND c.active = 1");
$sent = 0;
foreach ($stmt->fetchAll() as $invoice) {
    $body = 'Reminder: invoice ' . $invoice['invoice_number'] . ' has ' . $invoice['pending_amount'] . ' pending and is due on ' . $invoice['due_date'] . '.';
    if (!$dryRun) { send_notification((string) $invoice['email'], 'Payment due in 3 days', $body); }
    echo $invoice['invoice_number'] . ': ' . $invoice['email'] . PHP_EOL; $sent++;
}
echo 'Reminder count: ' . $sent . PHP_EOL;
