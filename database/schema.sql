-- Client Approval Panel — final deployment schema
-- This file is the consolidated schema for a fresh installation.
-- Historical phase migrations are retained separately for reference only.
-- Import this file once in the target MySQL server before deploying the app.

CREATE DATABASE IF NOT EXISTS client_approval_panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE client_approval_panel;

CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employee', 'client') NOT NULL,
    permissions TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE user_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    permission_key VARCHAR(80) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_permission (user_id, permission_key),
    INDEX idx_user_permissions_user (user_id),
    CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE otp_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    otp VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_otp_user_expiry (user_id, expires_at),
    CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    url VARCHAR(500) NULL,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user_created (user_id, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE push_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    onesignal_player_id VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_push_subscriptions_user (user_id),
    CONSTRAINT fk_push_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    industry VARCHAR(100) NULL,
    user_id INT UNSIGNED NULL UNIQUE,
    added_by INT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_clients_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_clients_added_by FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE client_employees (
    client_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (client_id, employee_id),
    CONSTRAINT fk_client_employees_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_client_employees_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE client_details (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    field_value TEXT NULL,
    UNIQUE KEY uq_client_detail (client_id, field_name),
    CONSTRAINT fk_client_details_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE assets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    type ENUM('logo', 'photo', 'video', 'testimonial', 'brochure', 'other') NOT NULL DEFAULT 'other',
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_assets_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE content (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    content_type ENUM('video', 'static', 'carousel') NOT NULL,
    caption TEXT NULL,
    status ENUM('pending', 'approved', 'changes_requested') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_content_client_status (client_id, status),
    CONSTRAINT fk_content_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE content_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_id INT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_content_files_content FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comments_content FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE content_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    old_status ENUM('pending', 'approved', 'changes_requested') NULL,
    new_status ENUM('pending', 'approved', 'changes_requested') NOT NULL,
    note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_content_history_content (content_id, created_at),
    CONSTRAINT fk_content_history_content FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    day VARCHAR(12) NULL,
    login_time DATETIME NULL,
    logout_time DATETIME NULL,
    check_in DATETIME NULL,
    check_out DATETIME NULL,
    working_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
    status ENUM('present', 'absent', 'half_day', 'paid_leave', 'weekly_off', 'holiday') NOT NULL DEFAULT 'present',
    remarks VARCHAR(255) NULL,
    UNIQUE KEY uq_attendance_employee_date (employee_id, date),
    CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE leave_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_leave_employee_status (employee_id, status),
    CONSTRAINT fk_leave_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    assigned_to INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    due_date DATE NULL,
    status ENUM('pending', 'in_progress', 'completed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tasks_employee_status (assigned_to, status),
    CONSTRAINT fk_tasks_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tasks_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE time_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NULL,
    duration INT UNSIGNED NOT NULL DEFAULT 0,
    date DATE NOT NULL,
    INDEX idx_time_logs_employee_date (employee_id, date),
    INDEX idx_time_logs_task (task_id),
    CONSTRAINT fk_time_logs_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_time_logs_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE internal_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NULL,
    client_id INT UNSIGNED NULL,
    employee_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_internal_notes_task (task_id, created_at),
    INDEX idx_internal_notes_client (client_id, created_at),
    CONSTRAINT fk_internal_notes_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_internal_notes_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_internal_notes_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE content_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    plan_type ENUM('Monthly', 'Weekly') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    title VARCHAR(200) NOT NULL,
    planned_date DATE NULL,
    content_type ENUM('reel', 'static', 'carousel') NULL,
    notes TEXT NULL,
    goal TEXT NULL,
    content_targets TEXT NULL,
    status ENUM('Planned', 'In Progress', 'Completed') NOT NULL DEFAULT 'Planned',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_content_plans_client_period (client_id, period_start, period_end),
    CONSTRAINT fk_content_plans_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_plans_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE plan_feedback (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_plan_feedback_plan (plan_id, created_at),
    CONSTRAINT fk_plan_feedback_plan FOREIGN KEY (plan_id) REFERENCES content_plans(id) ON DELETE CASCADE,
    CONSTRAINT fk_plan_feedback_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE profile_update_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    requested_by INT UNSIGNED NOT NULL,
    requested_changes TEXT NOT NULL,
    status ENUM('Pending', 'Reviewed', 'Completed') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_profile_requests_client_status (client_id, status),
    CONSTRAINT fk_profile_requests_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_profile_requests_user FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activity_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NULL,
    action VARCHAR(255) NOT NULL,
    reference_type ENUM('content', 'client', 'login', 'task', 'other') NOT NULL DEFAULT 'other',
    reference_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_employee_created (employee_id, created_at),
    CONSTRAINT fk_activity_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE staff_master (
    employee_id INT UNSIGNED PRIMARY KEY,
    designation VARCHAR(100) NOT NULL DEFAULT 'Employee',
    joining_date DATE NULL,
    monthly_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    fixed_allowance DECIMAL(12,2) NOT NULL DEFAULT 0,
    deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method ENUM('Bank Transfer', 'UPI', 'Cash') NOT NULL DEFAULT 'Bank Transfer',
    payment_details VARCHAR(255) NULL,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    CONSTRAINT fk_staff_master_user FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE salary_payroll (
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
    payment_status ENUM('Paid', 'Pending') NOT NULL DEFAULT 'Pending',
    payment_date DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payroll_employee_month (employee_id, month_year),
    CONSTRAINT fk_payroll_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE client_payments (
    invoice_number VARCHAR(50) PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    client_name VARCHAR(150) NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    invoice_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_received DECIMAL(12,2) NOT NULL DEFAULT 0,
    pending_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_status ENUM('Paid', 'Partially Paid', 'Pending', 'Overdue') NOT NULL DEFAULT 'Pending',
    payment_date DATE NULL,
    payment_mode VARCHAR(50) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_client_payments_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE office_expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    expense_name VARCHAR(200) NOT NULL,
    category ENUM('Rent', 'Utilities', 'Software', 'Office Supplies', 'Tea-Snacks', 'Misc') NOT NULL DEFAULT 'Misc',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_mode VARCHAR(50) NULL,
    payment_status ENUM('Paid', 'Pending') NOT NULL DEFAULT 'Paid',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_expenses_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE cash_flow (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    transaction_type ENUM('Income', 'Expense') NOT NULL,
    category ENUM('Client Payment', 'Staff Salary', 'Office Expense', 'Owner In-Out') NOT NULL,
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
