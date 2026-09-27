USE client_approval_panel;

ALTER TABLE users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER role;
ALTER TABLE clients ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER added_by;

CREATE TABLE client_employees (
    client_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (client_id, employee_id),
    CONSTRAINT fk_client_employees_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_client_employees_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
