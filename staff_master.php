<?php
declare(strict_types=1);

define('SAAS_NO_GLOBAL_NAV', true); require_once __DIR__ . '/finance.php';
require_finance_admin();

$pdo = db();
$message = null;
$error = null;
$designations = ['Social Media Manager', 'Graphic Designer', 'Video Editor', 'Full-stack Developer', 'Employee'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        if (!$employeeId) {
            throw new RuntimeException('Select an employee.');
        }
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'employee'");
        $stmt->execute([$employeeId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Invalid employee.');
        }
        $designation = trim((string) ($_POST['designation'] ?? 'Employee'));
        if (!in_array($designation, $designations, true)) {
            throw new RuntimeException('Invalid designation.');
        }
        $sql = 'INSERT INTO staff_master (employee_id, designation, joining_date, monthly_salary, fixed_allowance, deduction, payment_method, payment_details, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE designation = VALUES(designation), joining_date = VALUES(joining_date), monthly_salary = VALUES(monthly_salary), fixed_allowance = VALUES(fixed_allowance), deduction = VALUES(deduction), payment_method = VALUES(payment_method), payment_details = VALUES(payment_details), status = VALUES(status)';
        $pdo->prepare($sql)->execute([$employeeId, $designation, $_POST['joining_date'] ?: null, (float) ($_POST['monthly_salary'] ?? 0), (float) ($_POST['fixed_allowance'] ?? 0), (float) ($_POST['deduction'] ?? 0), $_POST['payment_method'] ?? 'Bank Transfer', trim((string) ($_POST['payment_details'] ?? '')), $_POST['status'] ?? 'Active']);
        $message = 'Staff record saved.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$employees = $pdo->query("SELECT u.id, u.name, u.email, COALESCE(s.designation, 'Employee') designation, COALESCE(s.monthly_salary, 0) monthly_salary, COALESCE(s.fixed_allowance, 0) fixed_allowance, COALESCE(s.deduction, 0) deduction, COALESCE(s.status, 'Active') staff_status FROM users u LEFT JOIN staff_master s ON s.employee_id = u.id WHERE u.role = 'employee' ORDER BY u.name")->fetchAll();
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Staff Master</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/saas.css"></head><body class="saas-body"><div class="saas-shell"><aside class="saas-sidebar"><div class="brand"><span>MT</span> Mega Techzy</div><div class="nav-label">Workspace</div><a class="saas-nav" href="admin_dashboard.php">▦ <span>Overview</span></a><a class="saas-nav" href="finance_dashboard.php">◈ <span>Finance</span></a><a class="saas-nav" href="manage_clients.php">♙ <span>Clients</span></a><a class="saas-nav" href="manage_users.php">♟ <span>Users</span></a><div class="nav-label">Operations</div><a class="saas-nav" href="admin_attendance.php">◷ <span>Attendance</span></a><a class="saas-nav active" href="staff_master.php">♙ <span>Staff Master</span></a><a class="saas-nav" href="payroll.php">▤ <span>Payroll</span></a><a class="saas-nav" href="invoices.php">▧ <span>Invoices</span></a><a class="saas-nav" href="expenses.php">▣ <span>Expenses</span></a><a class="saas-nav" href="cashflow.php">↗ <span>Cash flow</span></a><div class="nav-label">Account</div><a class="saas-nav" href="logout.php">↪ <span>Log out</span></a></aside><main class="saas-main"><div class="d-flex justify-content-between"><h1 class="h3">Staff Master</h1><a href="admin_dashboard.php">Dashboard</a></div><?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post" class="card card-body mb-4"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="row g-3"><div class="col-md-3"><label class="form-label">Employee</label><select class="form-select" name="employee_id" required><option value="">Select</option><?php foreach ($employees as $employee): ?><option value="<?= (int) $employee['id'] ?>"><?= e($employee['name'] . ' (' . $employee['email'] . ')') ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label">Designation</label><select class="form-select" name="designation" required><?php foreach ($designations as $designation): ?><option><?= e($designation) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label">Joining date</label><input class="form-control" type="date" name="joining_date"></div><div class="col-md-2"><label class="form-label">Monthly salary</label><input class="form-control" type="number" step="0.01" name="monthly_salary" required></div><div class="col-md-2"><label class="form-label">Allowance</label><input class="form-control" type="number" step="0.01" name="fixed_allowance" value="0"></div><div class="col-md-2"><label class="form-label">Deduction</label><input class="form-control" type="number" step="0.01" name="deduction" value="0"></div><div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><option>Active</option><option>Inactive</option></select></div><div class="col-md-3"><label class="form-label">Payment method</label><select class="form-select" name="payment_method"><option>Bank Transfer</option><option>UPI</option><option>Cash</option></select></div><div class="col-md-6"><label class="form-label">Payment details</label><input class="form-control" name="payment_details"></div><div class="col-12"><button class="btn btn-primary">Save staff record</button></div></div></form><div class="card card-body"><div class="table-responsive"><table class="table"><thead><tr><th>Employee</th><th>Designation</th><th>Salary</th><th>Allowance</th><th>Deduction</th><th>Status</th></tr></thead><tbody><?php foreach ($employees as $employee): ?><tr><td><?= e($employee['name']) ?></td><td><?= e($employee['designation']) ?></td><td><?= inr($employee['monthly_salary']) ?></td><td><?= inr($employee['fixed_allowance']) ?></td><td><?= inr($employee['deduction']) ?></td><td><?= e($employee['staff_status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></main></div></body></html>
