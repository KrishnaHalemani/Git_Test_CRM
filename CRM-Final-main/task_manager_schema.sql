-- Task Manager Database Schema
-- Add these tables to your existing CRM database

-- Tasks Table
CREATE TABLE IF NOT EXISTS tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_by INT NOT NULL,
    assigned_to INT NOT NULL,
    due_date DATETIME,
    start_date DATETIME,
    completed_at DATETIME,
    
    -- Link to other entities
    related_type ENUM('lead', 'contact', 'company', 'deal', 'general') DEFAULT 'general',
    related_id INT,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    KEY idx_assigned_to (assigned_to),
    KEY idx_created_by (created_by),
    KEY idx_status (status),
    KEY idx_priority (priority),
    KEY idx_due_date (due_date),
    KEY idx_related (related_type, related_id),
    KEY idx_created_at (created_at),
    
    -- Foreign key constraints
    CONSTRAINT fk_task_assigned_user FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_task_created_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Task Comments Table
CREATE TABLE IF NOT EXISTS task_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    KEY idx_task_id (task_id),
    KEY idx_user_id (user_id),
    KEY idx_created_at (created_at),
    
    -- Foreign keys
    CONSTRAINT fk_comment_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_comment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Task Reminders Table
CREATE TABLE IF NOT EXISTS task_reminders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    reminder_time DATETIME NOT NULL,
    reminder_type ENUM('due_today', 'overdue', 'daily_summary', 'custom') DEFAULT 'custom',
    is_sent BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    KEY idx_task_id (task_id),
    KEY idx_user_id (user_id),
    KEY idx_reminder_time (reminder_time),
    KEY idx_is_sent (is_sent),
    
    -- Foreign keys
    CONSTRAINT fk_reminder_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_reminder_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Task Activity Log Table (for audit trail)
CREATE TABLE IF NOT EXISTS task_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL, -- 'created', 'updated', 'status_changed', 'assigned', 'commented', 'deleted'
    old_value LONGTEXT,
    new_value LONGTEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    KEY idx_task_id (task_id),
    KEY idx_user_id (user_id),
    KEY idx_action (action),
    KEY idx_created_at (created_at),
    
    -- Foreign keys
    CONSTRAINT fk_activity_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index optimization for common queries
CREATE INDEX idx_task_assigned_status ON tasks(assigned_to, status);
CREATE INDEX idx_task_created_status ON tasks(created_by, status);
CREATE INDEX idx_task_due_date_status ON tasks(due_date, status);
