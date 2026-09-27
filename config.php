<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'client_approval_panel';
const DB_USER = 'root';
const DB_PASS = '';
const SMTP_HOST = 'mail.megatechzy.com';
const SMTP_PORT = 587;
const SMTP_ENCRYPTION = 'tls';
const MAIL_FROM = 'noreply@megatechzy.com';
const OTP_ENABLED = true;
const ONESIGNAL_APP_ID = '38fbfa89-0e2b-4692-9675-5b07ccaebe4e';
$onesignalRestApiKey = (string) (getenv('ONESIGNAL_REST_API_KEY') ?: '');
$onesignalSecretFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'client-approval-panel-secrets.php';
$smtpUsername = (string) (getenv('SMTP_USERNAME') ?: '');
$smtpPassword = (string) (getenv('SMTP_PASSWORD') ?: '');
if (is_file($onesignalSecretFile)) {
    $onesignalSecrets = require $onesignalSecretFile;
    $onesignalRestApiKey = $onesignalRestApiKey ?: (string) ($onesignalSecrets['onesignal_rest_api_key'] ?? '');
    $smtpUsername = $smtpUsername ?: (string) ($onesignalSecrets['smtp_username'] ?? '');
    $smtpPassword = $smtpPassword ?: (string) ($onesignalSecrets['smtp_password'] ?? '');
}
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
