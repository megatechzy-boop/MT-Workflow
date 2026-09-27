<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_role('admin');
define('SAAS_NO_GLOBAL_NAV', true);
require_once __DIR__ . '/finance.php';

$pdo = db();
$message = null;
$error = null;
$editClient = null;
$assignedEmployeeIds = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    try {
        if ($action === 'save') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $industry = trim((string) ($_POST['industry'] ?? ''));
            $userId = (int) ($_POST['user_id'] ?? 0) ?: null;
            $employeeIds = array_values(array_unique(array_filter(array_map('intval', $_POST['employee_ids'] ?? []))));

            if ($name === '') {
                throw new RuntimeException('Client name is required.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE clients SET name = ?, industry = ?, user_id = ? WHERE id = ?');
                $stmt->execute([$name, $industry ?: null, $userId, $id]);
                $clientId = $id;
                $pdo->prepare('DELETE FROM client_employees WHERE client_id = ?')->execute([$clientId]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO clients (name, industry, user_id, added_by) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $industry ?: null, $userId, $_SESSION['user']['id']]);
                $clientId = (int) $pdo->lastInsertId();
            }

            $assign = $pdo->prepare('INSERT INTO client_employees (client_id, employee_id) VALUES (?, ?)');
            foreach ($employeeIds as $employeeId) {
                $assign->execute([$clientId, $employeeId]);
            }
            $message = $id > 0 ? 'Client updated.' : 'Client created.';
        } elseif ($action === 'toggle' && $id > 0) {
            $pdo->prepare('UPDATE clients SET active = IF(active = 1, 0, 1) WHERE id = ?')->execute([$id]);
            $message = 'Client status updated.';
        }
    } catch (PDOException $e) {
        $error = $e->getCode() === '23000' ? 'That client login is already linked to another client.' : 'Database error.';
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT id, name, industry, user_id, active FROM clients WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editClient = $stmt->fetch() ?: null;
    if ($editClient) {
        $stmt = $pdo->prepare('SELECT employee_id FROM client_employees WHERE client_id = ?');
        $stmt->execute([(int) $editClient['id']]);
        $assignedEmployeeIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

$clientUsers = $pdo->query("SELECT id, name, email FROM users WHERE role = 'client' AND active = 1 AND (id NOT IN (SELECT user_id FROM clients WHERE user_id IS NOT NULL) OR id = " . (int) ($editClient['user_id'] ?? 0) . ") ORDER BY name")->fetchAll();
$employees = $pdo->query("SELECT id, name FROM users WHERE role = 'employee' AND active = 1 ORDER BY name")->fetchAll();
$clients = $pdo->query('SELECT c.id, c.name, c.industry, c.active, u.name AS login_name, COUNT(ce.employee_id) AS employee_count FROM clients c LEFT JOIN users u ON u.id = c.user_id LEFT JOIN client_employees ce ON ce.client_id = c.id GROUP BY c.id, c.name, c.industry, c.active, u.name ORDER BY c.name')->fetchAll();
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Manage Clients</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/saas.css"></head><body class="saas-body"><div class="saas-shell"><aside class="saas-sidebar"><div class="brand"><span>MT</span> Mega Techzy</div><div class="nav-label">Workspace</div><a class="saas-nav" href="admin_dashboard.php">▦ <span>Overview</span></a><a class="saas-nav" href="finance_dashboard.php">◈ <span>Finance</span></a><a class="saas-nav active" href="manage_clients.php">♙ <span>Clients</span></a><a class="saas-nav" href="manage_users.php">♟ <span>Users</span></a><div class="nav-label">Operations</div><a class="saas-nav" href="admin_attendance.php">◷ <span>Attendance</span></a><a class="saas-nav" href="staff_master.php">♙ <span>Staff Master</span></a><a class="saas-nav" href="payroll.php">▤ <span>Payroll</span></a><a class="saas-nav" href="invoices.php">▧ <span>Invoices</span></a><a class="saas-nav" href="expenses.php">▣ <span>Expenses</span></a><a class="saas-nav" href="cashflow.php">↗ <span>Cash flow</span></a><div class="nav-label">Account</div><a class="saas-nav" href="logout.php">↪ <span>Log out</span></a></aside><main class="saas-main"><div class="saas-topbar"><div><h1 class="saas-title">Clients</h1><div class="saas-subtitle">Create clients, link logins and assign employees.</div></div><a class="btn-outline-indigo" href="admin_dashboard.php">Overview</a></div>
<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card mb-4"><div class="card-body"><h2 class="h5"><?= $editClient ? 'Edit client' : 'Create client' ?></h2>
<form method="post" class="row g-3"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($editClient['id'] ?? 0) ?>">
<div class="col-md-4"><label class="form-label">Client name</label><input class="form-control" name="name" required value="<?= e($editClient['name'] ?? '') ?>"></div>
<div class="col-md-3"><label class="form-label">Industry</label><input class="form-control" name="industry" value="<?= e($editClient['industry'] ?? '') ?>"></div>
<div class="col-md-5"><label class="form-label">Client login</label><select class="form-select" name="user_id"><option value="">No login linked</option><?php foreach ($clientUsers as $user): ?><option value="<?= (int) $user['id'] ?>" <?= ((int) ($editClient['user_id'] ?? 0) === (int) $user['id']) ? 'selected' : '' ?>><?= e($user['name'] . ' (' . $user['email'] . ')') ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">Assigned employees</label><div class="row"><?php foreach ($employees as $employee): ?><div class="col-md-3"><label class="form-check"><input class="form-check-input" type="checkbox" name="employee_ids[]" value="<?= (int) $employee['id'] ?>" <?= in_array((int) $employee['id'], $assignedEmployeeIds, true) ? 'checked' : '' ?>> <?= e($employee['name']) ?></label></div><?php endforeach; ?></div></div>
<div class="col-12"><button class="btn btn-primary" type="submit"><?= $editClient ? 'Update' : 'Create' ?></button><?php if ($editClient): ?> <a class="btn btn-secondary" href="manage_clients.php">Cancel</a><?php endif; ?></div></form></div></div>
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Client</th><th>Industry</th><th>Login</th><th>Employees</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($clients as $client): ?><tr><td><?= e($client['name']) ?></td><td><?= e($client['industry'] ?? '') ?></td><td><?= e($client['login_name'] ?? 'Not linked') ?></td><td><?= (int) $client['employee_count'] ?></td><td><?= (int) $client['active'] === 1 ? 'Active' : 'Inactive' ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int) $client['id'] ?>">Edit</a> <form class="d-inline" method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $client['id'] ?>"><button class="btn btn-sm btn-outline-warning" type="submit"><?= (int) $client['active'] === 1 ? 'Deactivate' : 'Activate' ?></button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div></div></main></div></body></html>
