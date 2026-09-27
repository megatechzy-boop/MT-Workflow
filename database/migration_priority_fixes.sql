CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES
('working_days_mode', 'weekdays'),
('holidays', '[]'),
('company_name', 'MT Mega Techzy'),
('expense_categories', '["Rent","Utilities","Software","Office Supplies","Tea-Snacks","Misc"]')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

ALTER TABLE content_plans
    ADD COLUMN IF NOT EXISTS planned_date DATE NULL AFTER title,
    ADD COLUMN IF NOT EXISTS content_type ENUM('reel','static','carousel') NULL AFTER planned_date,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER content_type;
