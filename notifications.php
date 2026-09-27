<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function create_in_app_notification(int $userId, string $title, string $message, string $url = ''): void
{
    try {
        db()->prepare('INSERT INTO notifications (user_id, title, message, url) VALUES (?, ?, ?, ?)')->execute([$userId, $title, $message, $url ?: null]);
    } catch (Throwable $exception) {
        error_log('In-app notification failed: ' . $exception->getMessage());
    }
}

function sendPushNotification(int $userId, string $title, string $message, string $url = ''): void
{
    create_in_app_notification($userId, $title, $message, $url);
    if (ONESIGNAL_REST_API_KEY === '' || !function_exists('curl_init')) return;
    $payload = ['app_id' => ONESIGNAL_APP_ID, 'target_channel' => 'push', 'include_aliases' => ['external_id' => [(string) $userId]], 'headings' => ['en' => $title], 'contents' => ['en' => $message]];
    if ($url !== '') $payload['url'] = $url;
    $curl = curl_init('https://api.onesignal.com/notifications');
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8', 'Authorization: Key ' . ONESIGNAL_REST_API_KEY], CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES), CURLOPT_TIMEOUT => 15]);
    $response = curl_exec($curl);
    if ($response === false || curl_getinfo($curl, CURLINFO_HTTP_CODE) >= 300) error_log('OneSignal push failed: ' . ($response ?: curl_error($curl)));
    curl_close($curl);
}

function smtp_read($socket): string
{
    $response = '';
    while (($line = fgets($socket, 512)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function smtp_command($socket, string $command, array $accepted): void
{
    fwrite($socket, $command . "\r\n");
    $response = smtp_read($socket);
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $accepted, true)) {
        throw new RuntimeException('SMTP command failed with code ' . $code);
    }
}

function send_notification(string $to, string $subject, string $message): void
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $socket = @stream_socket_client('tcp://' . SMTP_HOST . ':' . SMTP_PORT, $errno, $error, 15, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        error_log('SMTP connection failed: ' . $error);
        notify_user_by_email($to, $subject, $message);
        return;
    }

    stream_set_timeout($socket, 15);
    try {
        $greeting = smtp_read($socket);
        if ((int) substr($greeting, 0, 3) !== 220) {
            throw new RuntimeException('SMTP greeting failed.');
        }
        smtp_command($socket, 'EHLO localhost', [250]);
        smtp_command($socket, 'STARTTLS', [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('SMTP TLS negotiation failed.');
        }
        smtp_command($socket, 'EHLO localhost', [250]);
        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode(SMTP_USERNAME), [334]);
        smtp_command($socket, base64_encode(SMTP_PASSWORD), [235]);
        smtp_command($socket, 'MAIL FROM:<' . MAIL_FROM . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $safeSubject = str_replace(["\r", "\n"], '', $subject);
        $body = str_replace(["\r\n", "\r"], "\n", $message);
        $body = preg_replace('/^\./m', '..', $body) ?? $body;
        $headers = [
            'From: ' . MAIL_FROM,
            'To: ' . $to,
            'Subject: ' . $safeSubject,
            'Date: ' . date(DATE_RFC2822),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $body) . "\r\n.\r\n");
        $response = smtp_read($socket);
        if ((int) substr($response, 0, 3) !== 250) {
            throw new RuntimeException('SMTP message rejected.');
        }
        smtp_command($socket, 'QUIT', [221]);
    } catch (Throwable $exception) {
        error_log('SMTP delivery failed: ' . $exception->getMessage());
    } finally {
        fclose($socket);
    }
    notify_user_by_email($to, $subject, $message);
}

function notify_user_by_email(string $to, string $subject, string $message, string $url = ''): void
{
    try {
        if ($url === '') {
            $url = match (true) {
                str_starts_with($subject, 'Content is ready') => 'client_dashboard.php',
                $subject === 'Content status updated' => 'approve_content.php',
                str_starts_with($subject, 'Invoice ') => 'my_invoices.php',
                $subject === 'Temporary password' => 'login.php',
                $subject === 'Your login verification code' => 'login.php',
                default => '',
            };
        }
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND active = 1 LIMIT 1');
        $stmt->execute([$to]);
        $userId = $stmt->fetchColumn();
        if ($userId !== false) sendPushNotification((int) $userId, $subject, $message, $url);
    } catch (Throwable $exception) {
        error_log('Push recipient lookup failed: ' . $exception->getMessage());
    }
}
