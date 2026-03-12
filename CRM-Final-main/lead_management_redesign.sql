-- Redesign Lead Management Schema

-- 1. Add indexes for performance scaling
ALTER TABLE `leads` ADD INDEX `idx_email` (`email`);
ALTER TABLE `leads` ADD INDEX `idx_phone` (`phone`);
ALTER TABLE `leads` ADD INDEX `idx_status` (`status`);
ALTER TABLE `leads` ADD INDEX `idx_assigned_to` (`assigned_to`);
ALTER TABLE `leads` ADD INDEX `idx_created_at` (`created_at`);

-- 2. Create lead_campaigns bridge table
CREATE TABLE IF NOT EXISTS `lead_campaigns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT NOT NULL,
    `campaign_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `ux_lead_campaign` (`lead_id`, `campaign_id`),
    FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Update status enum if needed (optional, but good for consistency)
-- ALTER TABLE `leads` MODIFY COLUMN `status` ENUM('new', 'contacted', 'qualified', 'proposal_sent', 'converted', 'lost') DEFAULT 'new';