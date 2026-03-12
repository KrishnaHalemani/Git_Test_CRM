--
-- New Lead Management Schema
-- This script redesigns the lead management system for scalability and many-to-many campaign relationships.
--

-- Step 1: Modify the existing `leads` table.
-- This adds an `updated_at` timestamp and changes the `status` column to the new workflow.
ALTER TABLE `leads` 
    ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`,
    MODIFY COLUMN `status` ENUM('new', 'contacted', 'qualified', 'proposal_sent', 'converted', 'lost') NOT NULL DEFAULT 'new';

-- Step 2: Add performance indexes to the `leads` table.
-- This will significantly speed up searching and duplicate checking.
-- We use a procedure to avoid errors if the indexes already exist.
DROP PROCEDURE IF EXISTS AddIndexIfNotExists;
CREATE PROCEDURE AddIndexIfNotExists(
    IN t_name VARCHAR(128), 
    IN i_name VARCHAR(128), 
    IN c_name VARCHAR(255)
)
BEGIN
    IF NOT EXISTS(SELECT * FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = t_name AND index_name = i_name) THEN
        SET @s = CONCAT('CREATE INDEX ', i_name, ' ON ', t_name, '(', c_name, ')');
        PREPARE stmt FROM @s;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END;

CALL AddIndexIfNotExists('leads', 'idx_email', 'email(191)');
CALL AddIndexIfNotExists('leads', 'idx_phone', 'phone');
DROP PROCEDURE AddIndexIfNotExists;

-- Step 3: Create the `lead_campaigns` bridge table.
-- This table creates a many-to-many relationship between leads and campaigns.
CREATE TABLE IF NOT EXISTS `lead_campaigns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT NOT NULL,
    `campaign_id` INT NOT NULL,
    `imported_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `ux_lead_campaign` (`lead_id`, `campaign_id`),
    FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 4: Migrate existing campaign data to the new bridge table.
-- This ensures no data is lost from the old `leads.campaign_id` column.
INSERT IGNORE INTO lead_campaigns (lead_id, campaign_id, imported_at)
SELECT id, campaign_id, created_at FROM leads WHERE campaign_id IS NOT NULL AND campaign_id > 0;

-- Step 5: IMPORTANT - After verifying the migration, you can remove the old `campaign_id` column from the `leads` table.
-- This step is commented out for safety. Run it manually when you are ready.
-- ALTER TABLE `leads` DROP FOREIGN KEY `leads_ibfk_campaign`;
-- ALTER TABLE `leads` DROP COLUMN `campaign_id`;