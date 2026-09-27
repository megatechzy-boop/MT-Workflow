USE client_approval_panel;

-- New installations should import database/schema.sql.
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
