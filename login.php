<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/notifications.php';

if (is_logged_in()) { header('Location: index.php'); exit; }
$error = null; $success = (string) ($_SESSION['otp_flash'] ?? ''); unset($_SESSION['otp_flash']); $otpMode = isset($_SESSION['otp_user_id']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $pdo = db();
        if ($otpMode) {
            if (($_POST['action'] ?? '') === 'resend_otp') {
                $userId = (int) ($_SESSION['otp_user_id'] ?? 0);
                $stmt = $pdo->prepare("SELECT id, email, role, active FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]); $user = $stmt->fetch();
                if (!$user || (int) $user['active'] !== 1 || !in_array($user['role'], ['employee', 'client'], true)) {
                    unset($_SESSION['otp_user_id']);
                    throw new RuntimeException('OTP session expired. Please sign in again.');
                }
                $lastSent = (int) ($_SESSION['otp_last_sent'] ?? 0);
                if ($lastSent > time() - 30) {
                    throw new RuntimeException('Please wait 30 seconds before requesting another OTP.');
                }
                $plainOtp = (string) random_int(100000, 999999);
                $pdo->prepare('DELETE FROM otp_codes WHERE user_id = ?')->execute([$userId]);
                $pdo->prepare('INSERT INTO otp_codes (user_id, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))')->execute([$userId, password_hash($plainOtp, PASSWORD_DEFAULT)]);
                send_notification((string) $user['email'], 'Your login verification code', 'Your one-time login code is ' . $plainOtp . '. It expires in 10 minutes.');
                $_SESSION['otp_last_sent'] = time();
                $_SESSION['otp_flash'] = 'A new verification code was sent to your email.';
                header('Location: login.php');
                exit;
            }
            $otp = trim((string) ($_POST['otp'] ?? ''));
            $userId = (int) ($_SESSION['otp_user_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT id, name, email, password, role, permissions, active FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]); $user = $stmt->fetch();
            $codeStmt = $pdo->prepare('SELECT id, otp FROM otp_codes WHERE user_id = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
            $codeStmt->execute([$userId]); $code = $codeStmt->fetch();
            if (!$user || !$code || !preg_match('/^\d{6}$/', $otp) || !password_verify($otp, $code['otp'])) { throw new RuntimeException('Invalid or expired OTP.'); }
            $pdo->prepare('DELETE FROM otp_codes WHERE user_id = ?')->execute([$userId]);
            unset($_SESSION['otp_user_id']);
            unset($_SESSION['otp_last_sent']);
            session_regenerate_id(true); unset($user['password']); $_SESSION['user'] = $user;
            if ($user['role'] === 'employee') { $pdo->prepare("INSERT INTO attendance (employee_id, date, login_time, status) VALUES (?, CURDATE(), NOW(), 'present') ON DUPLICATE KEY UPDATE login_time = COALESCE(login_time, NOW())")->execute([$user['id']]); log_activity((int) $user['id'], 'Logged in', 'login'); }
            header('Location: index.php'); exit;
        }
        $email = trim((string) ($_POST['email'] ?? '')); $password = (string) ($_POST['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') { throw new RuntimeException('Enter a valid email address and password.'); }
        $stmt = $pdo->prepare('SELECT id, name, email, password, role, permissions, active FROM users WHERE email = ? LIMIT 1'); $stmt->execute([$email]); $user = $stmt->fetch();
        if (!$user || (int) $user['active'] !== 1 || !password_verify($password, $user['password'])) { throw new RuntimeException('Invalid email or password.'); }
        if (OTP_ENABLED && in_array($user['role'], ['employee', 'client'], true)) {
            $plainOtp = (string) random_int(100000, 999999);
            $pdo->prepare('DELETE FROM otp_codes WHERE user_id = ?')->execute([$user['id']]);
            $pdo->prepare('INSERT INTO otp_codes (user_id, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))')->execute([$user['id'], password_hash($plainOtp, PASSWORD_DEFAULT)]);
            send_notification((string) $user['email'], 'Your login verification code', 'Your one-time login code is ' . $plainOtp . '. It expires in 10 minutes.');
            $_SESSION['otp_user_id'] = (int) $user['id']; $_SESSION['otp_last_sent'] = time(); $otpMode = true;
        } else {
            session_regenerate_id(true); unset($user['password']); $_SESSION['user'] = $user; header('Location: index.php'); exit;
        }
    } catch (Throwable $exception) { $error = $exception->getMessage(); }
}
?>
<script src="assets/js/onesignal.js?v=1" defer></script>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Client Approval Panel - Login</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5" style="max-width:480px"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h4 mb-4">Client Approval Panel</h1><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?><?php if ($otpMode): ?><p class="text-muted">A 6-digit verification code was sent to your email.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="form-label" for="otp">Verification code</label><input class="form-control mb-4" id="otp" name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus><button class="btn btn-primary w-100">Verify OTP</button></form><form method="post" class="mt-2"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="resend_otp"><button class="btn btn-outline-secondary w-100" type="submit">Resend OTP</button></form><a class="d-block text-center mt-3" href="logout.php">Start over</a><?php else: ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="form-label" for="email">Email</label><input class="form-control mb-3" id="email" name="email" type="email" required autofocus><label class="form-label" for="password">Password</label><input class="form-control mb-4" id="password" name="password" type="password" required><button class="btn btn-primary w-100">Sign in</button></form><?php endif; ?></div></div></main></body></html>
