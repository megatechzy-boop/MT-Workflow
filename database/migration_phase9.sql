USE client_approval_panel;

-- New installations should import database/schema.sql instead.
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
