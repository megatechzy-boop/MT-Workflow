-- Advanced employee features migration for an existing installation.
-- Run once after the base schema/migrations.

CREATE TABLE IF NOT EXISTS time_logs (
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

CREATE TABLE IF NOT EXISTS internal_notes (
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

ALTER TABLE content_files ADD COLUMN IF NOT EXISTS version SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER sort_order;

CREATE TABLE IF NOT EXISTS otp_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    otp VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_otp_user_expiry (user_id, expires_at),
    CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS plan_feedback (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_plan_feedback_plan (plan_id, created_at),
    CONSTRAINT fk_plan_feedback_plan FOREIGN KEY (plan_id) REFERENCES content_plans(id) ON DELETE CASCADE,
    CONSTRAINT fk_plan_feedback_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS profile_update_requests (
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
