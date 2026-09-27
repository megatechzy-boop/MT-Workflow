USE client_approval_panel;

-- For existing databases only. New installations should import database/schema.sql.
ALTER TABLE attendance
    ADD COLUMN day VARCHAR(12) NULL AFTER date,
    ADD COLUMN check_in DATETIME NULL AFTER logout_time,
    ADD COLUMN check_out DATETIME NULL AFTER check_in,
    ADD COLUMN working_hours DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER check_out,
    ADD COLUMN remarks VARCHAR(255) NULL AFTER status;

ALTER TABLE attendance MODIFY status ENUM('present','absent','half_day','paid_leave','weekly_off','holiday') NOT NULL DEFAULT 'present';

CREATE TABLE staff_master (
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

-- Import the final schema.sql for the remaining F3-F5 finance tables.
