CREATE TABLE IF NOT EXISTS campaign_user_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    lead_target INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY ux_campaign_user_target (campaign_id, user_id),
    INDEX idx_campaign_target_campaign (campaign_id),
    INDEX idx_campaign_target_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE lead_assignments
    ADD COLUMN source ENUM('campaign_auto', 'manual', 'reassigned') DEFAULT 'manual' AFTER assigned_at;
