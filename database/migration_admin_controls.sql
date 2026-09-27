-- Admin controls: normalized employee permissions.
CREATE TABLE IF NOT EXISTS user_permissions (
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

INSERT INTO user_permissions (user_id, permission_key, enabled)
SELECT u.id, p.permission_key, 1
FROM users u
CROSS JOIN (
    SELECT 'manage_plans' AS permission_key UNION ALL
    SELECT 'upload_content' UNION ALL
    SELECT 'view_clients' UNION ALL
    SELECT 'view_tasks' UNION ALL
    SELECT 'view_payslip' UNION ALL
    SELECT 'apply_leave' UNION ALL
    SELECT 'view_activity'
) p
WHERE u.role = 'employee'
  AND JSON_VALID(u.permissions)
  AND JSON_CONTAINS(u.permissions, JSON_QUOTE(p.permission_key))
ON DUPLICATE KEY UPDATE enabled = VALUES(enabled);
