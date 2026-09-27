USE client_approval_panel;

ALTER TABLE users ADD COLUMN IF NOT EXISTS permissions TEXT NULL AFTER role;

ALTER TABLE attendance
    ADD COLUMN day VARCHAR(12) NULL AFTER date,
    ADD COLUMN check_in DATETIME NULL AFTER logout_time,
    ADD COLUMN check_out DATETIME NULL AFTER check_in,
    ADD COLUMN working_hours DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER check_out,
    ADD COLUMN remarks VARCHAR(255) NULL AFTER status;

ALTER TABLE attendance MODIFY status ENUM('present','absent','half_day','leave','paid_leave','weekly_off','holiday') NOT NULL DEFAULT 'present';

CREATE TABLE IF NOT EXISTS staff_master (
    employee_id INT UNSIGNED PRIMARY KEY,
    joining_date DATE NULL,
    monthly_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    fixed_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method ENUM('Bank Transfer','UPI','Cash') NOT NULL DEFAULT 'Bank Transfer',
    payment_details VARCHAR(255) NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    CONSTRAINT fk_staff_master_user FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS salary_payroll (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    month_year CHAR(7) NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    working_days DECIMAL(5,2) NOT NULL DEFAULT 0,
    present_days DECIMAL(5,2) NOT NULL DEFAULT 0,
    half_days DECIMAL(5,2) NOT NULL DEFAULT 0,
    paid_leaves DECIMAL(5,2) NOT NULL DEFAULT 0,
    absent_days DECIMAL(5,2) NOT NULL DEFAULT 0,
    payable_days DECIMAL(5,2) NOT NULL DEFAULT 0,
    basic_salary_payable DECIMAL(12,2) NOT NULL DEFAULT 0,
    allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_status ENUM('Paid','Pending') NOT NULL DEFAULT 'Pending',
    payment_date DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payroll_employee_month (employee_id, month_year),
    CONSTRAINT fk_payroll_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS client_payments (
    invoice_number VARCHAR(50) PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    client_name VARCHAR(150) NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    invoice_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_received DECIMAL(12,2) NOT NULL DEFAULT 0,
    pending_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_status ENUM('Paid','Partially Paid','Pending','Overdue') NOT NULL DEFAULT 'Pending',
    payment_date DATE NULL,
    payment_mode VARCHAR(50) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_client_payments_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS office_expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    expense_name VARCHAR(200) NOT NULL,
    category ENUM('Rent','Utilities','Software','Office Supplies','Tea-Snacks','Misc') NOT NULL DEFAULT 'Misc',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_mode VARCHAR(50) NULL,
    payment_status ENUM('Paid','Pending') NOT NULL DEFAULT 'Paid',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_expenses_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cash_flow (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    transaction_type ENUM('Income','Expense') NOT NULL,
    category ENUM('Client Payment','Staff Salary','Office Expense','Owner In-Out') NOT NULL,
    description VARCHAR(255) NOT NULL,
    money_in DECIMAL(12,2) NOT NULL DEFAULT 0,
    money_out DECIMAL(12,2) NOT NULL DEFAULT 0,
    running_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    reference VARCHAR(100) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cash_flow_date (date, id),
    CONSTRAINT fk_cash_flow_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
ALTER TABLE staff_master ADD COLUMN IF NOT EXISTS designation VARCHAR(100) NOT NULL DEFAULT 'Employee' AFTER employee_id;

CREATE TABLE IF NOT EXISTS content_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    plan_type ENUM('Monthly', 'Weekly') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    title VARCHAR(200) NOT NULL,
    goal TEXT NULL,
    content_targets TEXT NULL,
    status ENUM('Planned', 'In Progress', 'Completed') NOT NULL DEFAULT 'Planned',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_content_plans_client_period (client_id, period_start, period_end),
    CONSTRAINT fk_content_plans_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_plans_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
