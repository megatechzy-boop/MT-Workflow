<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_role('admin');
define('SAAS_NO_GLOBAL_NAV', true);
require_once __DIR__ . '/finance.php';
require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/notifications.php';

$pdo = db();
$message = null;
$error = null;
$editUser = null;
$permissionOptions = available_permissions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    try {
        verify_csrf();
        if ($action === 'save') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $role = (string) ($_POST['role'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $permissions = array_values(array_intersect(array_keys($permissionOptions), array_map('strval', $_POST['permissions'] ?? [])));
            $permissionsJson = json_encode($role === 'employee' ? $permissions : [], JSON_THROW_ON_ERROR);

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['employee', 'client'], true)) {
                throw new RuntimeException('Enter a valid name, email, and role.');
            }

            if ($id > 0) {
                if ($password !== '') {
                    $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, role = ?, password = ?, permissions = ? WHERE id = ? AND role <> \'admin\'');
                    $stmt->execute([$name, $email, $role, password_hash($password, PASSWORD_DEFAULT), $permissionsJson, $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, role = ?, permissions = ? WHERE id = ? AND role <> \'admin\'');
                    $stmt->execute([$name, $email, $role, $permissionsJson, $id]);
                }
                $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$id]);
                $message = 'User updated.';
            } else {
                if ($password === '') {
                    throw new RuntimeException('A password is required for a new user.');
                }
                $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, permissions) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $permissionsJson]);
                $id = (int) $pdo->lastInsertId();
                $message = 'User created.';
            }
            if ($role === 'employee') {
                $permissionStmt = $pdo->prepare('INSERT INTO user_permissions (user_id, permission_key, enabled) VALUES (?, ?, 1)');
                foreach ($permissions as $permission) { $permissionStmt->execute([$id, $permission]); }
            }
            log_activity((int) $_SESSION['user']['id'], $id > 0 && $action === 'save' ? 'Saved user account and permissions' : 'Saved user account', 'other', $id);
        } elseif ($action === 'toggle' && $id > 0) {
            $stmt = $pdo->prepare('UPDATE users SET active = IF(active = 1, 0, 1) WHERE id = ? AND role <> \'admin\'');
            $stmt->execute([$id]);
            $message = 'User status updated.';
            log_activity((int) $_SESSION['user']['id'], 'Changed user login status', 'other', $id);
        } elseif ($action === 'reset_password' && $id > 0) {
            $stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? AND role <> \'admin\' LIMIT 1');
            $stmt->execute([$id]); $target = $stmt->fetch();
            if (!$target) { throw new RuntimeException('User not found.'); }
            $temporaryPassword = bin2hex(random_bytes(5));
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($temporaryPassword, PASSWORD_DEFAULT), $id]);
            send_notification((string) $target['email'], 'Temporary password', "Hello {$target['name']},\n\nYour temporary password is: {$temporaryPassword}\nPlease sign in and change it as soon as possible.");
            $message = 'Temporary password generated and sent to the user email.';
            log_activity((int) $_SESSION['user']['id'], 'Reset user password', 'other', $id);
        }
    } catch (PDOException $e) {
        $error = $e->getCode() === '23000' ? 'That email address is already in use.' : 'Database error.';
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT id, name, email, role, permissions, active FROM users WHERE id = ? AND role <> \'admin\'');
    $stmt->execute([(int) $_GET['edit']]);
    $editUser = $stmt->fetch() ?: null;
    if ($editUser && $editUser['role'] === 'employee') {
        $permissionStmt = $pdo->prepare('SELECT permission_key FROM user_permissions WHERE user_id = ? AND enabled = 1');
        $permissionStmt->execute([$editUser['id']]);
        $storedPermissions = $permissionStmt->fetchAll(PDO::FETCH_COLUMN);
        if ($storedPermissions) { $editUser['permissions'] = json_encode($storedPermissions); }
    }
}

$users = $pdo->query("SELECT id, name, email, role, active, created_at FROM users WHERE role <> 'admin' ORDER BY name")->fetchAll();
$editPermissions = json_decode((string) ($editUser['permissions'] ?? ''), true);
$editPermissions = is_array($editPermissions) ? $editPermissions : array_keys($permissionOptions);
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Manage Users</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/saas.css"></head><body class="saas-body"><div class="saas-shell"><aside class="saas-sidebar"><div class="brand"><span>MT</span> Mega Techzy</div><div class="nav-label">Workspace</div><a class="saas-nav" href="admin_dashboard.php">▦ <span>Overview</span></a><a class="saas-nav" href="finance_dashboard.php">◈ <span>Finance</span></a><a class="saas-nav" href="manage_clients.php">♙ <span>Clients</span></a><a class="saas-nav active" href="manage_users.php">♟ <span>Users</span></a><div class="nav-label">Operations</div><a class="saas-nav" href="admin_attendance.php">◷ <span>Attendance</span></a><a class="saas-nav" href="staff_master.php">♙ <span>Staff Master</span></a><a class="saas-nav" href="payroll.php">▤ <span>Payroll</span></a><a class="saas-nav" href="invoices.php">▧ <span>Invoices</span></a><a class="saas-nav" href="expenses.php">▣ <span>Expenses</span></a><a class="saas-nav" href="cashflow.php">↗ <span>Cash flow</span></a><div class="nav-label">Account</div><a class="saas-nav" href="logout.php">↪ <span>Log out</span></a></aside><main class="saas-main"><div class="saas-topbar"><div><h1 class="saas-title">Users</h1><div class="saas-subtitle">Manage users, permissions and login access.</div></div><a class="btn-outline-indigo" href="admin_dashboard.php">Overview</a></div>
<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card mb-4"><div class="card-body"><h2 class="h5"><?= $editUser ? 'Edit user' : 'Create user' ?></h2>
<form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($editUser['id'] ?? 0) ?>">
<div class="col-md-4"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($editUser['name'] ?? '') ?>"></div>
<div class="col-md-4"><label class="form-label">Email</label><input class="form-control" name="email" type="email" required value="<?= e($editUser['email'] ?? '') ?>"></div>
<div class="col-md-2"><label class="form-label">Role</label><select class="form-select" name="role"><option value="employee" <?= (($editUser['role'] ?? '') === 'employee') ? 'selected' : '' ?>>Employee</option><option value="client" <?= (($editUser['role'] ?? '') === 'client') ? 'selected' : '' ?>>Client</option></select></div>
<div class="col-md-2"><label class="form-label">Password</label><input class="form-control" name="password" type="password" <?= $editUser ? '' : 'required' ?>></div>
<div class="col-12"><label class="form-label">Permissions</label><div class="row g-2"><?php foreach ($permissionOptions as $permission => $label): ?><div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="<?= e($permission) ?>" <?= in_array($permission, $editPermissions, true) ? 'checked' : '' ?>><span class="form-check-label"><?= e($label) ?></span></label></div><?php endforeach; ?></div><small class="text-muted">Permissions apply to employee accounts. Admin always has full access; client access remains limited to their own records.</small></div>
<div class="col-12"><button class="btn btn-primary" type="submit"><?= $editUser ? 'Update' : 'Create' ?></button><?php if ($editUser): ?> <a class="btn btn-secondary" href="manage_users.php">Cancel</a><?php endif; ?></div></form></div></div>
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($users as $user): ?><tr><td><?= e($user['name']) ?></td><td><?= e($user['email']) ?></td><td><?= e(ucfirst($user['role'])) ?></td><td><?= (int) $user['active'] === 1 ? 'Active' : 'Inactive' ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int) $user['id'] ?>">Edit</a> <form class="d-inline" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><button class="btn btn-sm btn-outline-warning" type="submit"><?= (int) $user['active'] === 1 ? 'Deactivate' : 'Activate' ?></button></form> <form class="d-inline" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><button class="btn btn-sm btn-outline-secondary" type="submit">Reset password</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div></div></main></div></body></html>
