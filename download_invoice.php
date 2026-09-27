<?php
declare(strict_types=1);
require_once __DIR__ . '/access.php';
require_role('client');
$invoiceNumber = trim((string) ($_GET['invoice_number'] ?? ''));
$stmt = db()->prepare('SELECT p.* FROM client_payments p INNER JOIN clients c ON c.id = p.client_id WHERE p.invoice_number = ? AND c.user_id = ? AND c.active = 1 LIMIT 1');
$stmt->execute([$invoiceNumber, $_SESSION['user']['id']]); $invoice = $stmt->fetch();
if (!$invoice) { http_response_code(404); exit('Invoice not found.'); }
function pdf_text(string $value): string { return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value); }
$overdue = (float) $invoice['pending_amount'] > 0 && $invoice['due_date'] < date('Y-m-d');
$lines = [
    'Mega Techzy - Invoice',
    'Invoice number: ' . $invoice['invoice_number'],
    'Invoice date: ' . $invoice['invoice_date'],
    'Due date: ' . $invoice['due_date'],
    'Client: ' . $invoice['client_name'],
    'Invoice amount: Rs. ' . number_format((float) $invoice['invoice_amount'], 2),
    'Amount received: Rs. ' . number_format((float) $invoice['amount_received'], 2),
    'Pending amount: Rs. ' . number_format((float) $invoice['pending_amount'], 2),
    'Status: ' . ($overdue ? 'Overdue' : $invoice['payment_status']),
];
$commands = ['BT', '/F1 16 Tf', '50 760 Td', '(' . pdf_text($lines[0]) . ') Tj', '/F1 11 Tf'];
foreach (array_slice($lines, 1) as $line) { $commands[] = '0 -24 Td'; $commands[] = '(' . pdf_text($line) . ') Tj'; }
$stream = implode("\n", $commands) . "\nET";
$objects = [
    '1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj',
    '2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj',
    '3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>endobj',
    '4 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj',
    '5 0 obj<< /Length ' . strlen($stream) . " >>stream\n" . $stream . "\nendstream\nendobj",
];
$pdf = "%PDF-1.4\n"; $offsets = [0];
foreach ($objects as $object) { $offsets[] = strlen($pdf); $pdf .= $object . "\n"; }
$xref = strlen($pdf); $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
for ($i = 1; $i <= count($objects); $i++) { $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]); }
$pdf .= "trailer<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="invoice-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $invoice['invoice_number']) . '.pdf"');
echo $pdf;
