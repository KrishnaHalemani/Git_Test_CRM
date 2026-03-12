--
-- Table structure for table `campaigns`
--
CREATE TABLE `campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','archived') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `campaigns_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `campaign_fields`
--
CREATE TABLE `campaign_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL COMMENT 'Human-readable label e.g. ''Source City''',
  `field_key` varchar(100) NOT NULL COMMENT 'Machine-readable key e.g. ''source_city''',
  `field_type` enum('text','number','date') NOT NULL DEFAULT 'text',
  PRIMARY KEY (`id`),
  KEY `campaign_id` (`campaign_id`),
  UNIQUE KEY `campaign_id_field_key` (`campaign_id`,`field_key`),
  CONSTRAINT `campaign_fields_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `lead_custom_data`
--
CREATE TABLE `lead_custom_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `campaign_field_id` int(11) NOT NULL,
  `value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_id_campaign_field_id` (`lead_id`,`campaign_field_id`),
  KEY `campaign_field_id` (`campaign_field_id`),
  CONSTRAINT `lead_custom_data_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_custom_data_ibfk_2` FOREIGN KEY (`campaign_field_id`) REFERENCES `campaign_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add a campaign_id to the leads table to associate a lead with a campaign
ALTER TABLE `leads` ADD `campaign_id` INT(11) NULL DEFAULT NULL AFTER `id`, ADD INDEX (`campaign_id`);
ALTER TABLE `leads` ADD CONSTRAINT `leads_ibfk_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL;