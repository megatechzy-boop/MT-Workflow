<?php
declare(strict_types=1);

const SMTP_HOST = 'mail.megatechzy.com';
const SMTP_PORT = 587;
const SMTP_ENCRYPTION = 'tls';
const MAIL_FROM = 'noreply@megatechzy.com';
const OTP_ENABLED = true;
const ONESIGNAL_APP_ID = '38fbfa89-0e2b-4692-9675-5b07ccaebe4e';
$onesignalRestApiKey = (string) (getenv('ONESIGNAL_REST_API_KEY') ?: '');
$dbHost = (string) (getenv('DB_HOST') ?: '127.0.0.1');
$dbName = (string) (getenv('DB_NAME') ?: 'client_approval_panel');
$dbUser = (string) (getenv('DB_USER') ?: 'root');
$dbPass = (string) (getenv('DB_PASS') ?: '');
$secretFileCandidates = [
    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'client-approval-panel-secrets.php',
    dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'client-approval-panel-secrets.php',
];
$smtpUsername = (string) (getenv('SMTP_USERNAME') ?: '');
$smtpPassword = (string) (getenv('SMTP_PASSWORD') ?: '');
foreach ($secretFileCandidates as $secretFile) {
    if (!is_file($secretFile)) {
        continue;
    }
    $onesignalSecrets = require $secretFile;
    $onesignalRestApiKey = $onesignalRestApiKey ?: (string) ($onesignalSecrets['onesignal_rest_api_key'] ?? '');
    $dbHost = (string) ($onesignalSecrets['db_host'] ?? $dbHost);
    $dbName = (string) ($onesignalSecrets['db_name'] ?? $dbName);
    $dbUser = (string) ($onesignalSecrets['db_user'] ?? $dbUser);
    $dbPass = (string) ($onesignalSecrets['db_pass'] ?? $dbPass);
    $smtpUsername = $smtpUsername ?: (string) ($onesignalSecrets['smtp_username'] ?? '');
    $smtpPassword = $smtpPassword ?: (string) ($onesignalSecrets['smtp_password'] ?? '');
    break;
}
define('DB_HOST', $dbHost);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('SMTP_USERNAME', $smtpUsername);
define('SMTP_PASSWORD', $smtpPassword);
define('ONESIGNAL_REST_API_KEY', $onesignalRestApiKey);

date_default_timezone_set('Asia/Kolkata');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
    ]);
    session_start();
}
