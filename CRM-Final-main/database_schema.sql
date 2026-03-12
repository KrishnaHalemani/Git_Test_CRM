-- CRM Pro Database Schema
-- Run this script in your MySQL database to create the required tables

-- Create database
CREATE DATABASE IF NOT EXISTS crm_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crm_pro;

-- Users table for authentication and user management
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('user', 'admin', 'superadmin') DEFAULT 'user',
    branch VARCHAR(100) DEFAULT 'Head Office',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- Leads table for storing lead information
CREATE TABLE leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    company VARCHAR(100),
    service VARCHAR(50),
    status ENUM('new', 'contacted', 'qualified', 'hot', 'converted', 'lost') DEFAULT 'new',
    source ENUM('website', 'social-media', 'referral', 'advertisement', 'manual', 'other') DEFAULT 'manual',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    assigned_to INT,
    created_by INT,
    notes TEXT,
    follow_up_date DATE NULL,
    conversion_date TIMESTAMP NULL,
    estimated_value DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_source (source),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_created_by (created_by),
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
);

-- Lead activities table for tracking interactions
CREATE TABLE lead_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    user_id INT NOT NULL,
    activity_type ENUM('call', 'email', 'meeting', 'note', 'status_change', 'follow_up') NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    activity_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_lead_id (lead_id),
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_activity_date (activity_date)
);

-- Companies table for better organization
CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    industry VARCHAR(50),
    website VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    country VARCHAR(50),
    postal_code VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_industry (industry)
);

-- Services table for managing available services
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    base_price DECIMAL(10,2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_category (category),
    INDEX idx_is_active (is_active)
);

-- Settings table for application configuration
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- Insert default users
INSERT INTO users (username, email, password_hash, full_name, role) VALUES
('superadmin', 'superadmin@crmPro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Administrator', 'superadmin'),
('admin', 'admin@crmPro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin'),
('user', 'user@crmPro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Regular User', 'user');

-- Insert default services
INSERT INTO services (name, description, category, base_price) VALUES
('Web Development', 'Custom website development and design', 'Development', 2500.00),
('Mobile App Development', 'iOS and Android mobile application development', 'Development', 5000.00),
('Digital Marketing', 'SEO, SEM, and social media marketing services', 'Marketing', 1500.00),
('Business Consulting', 'Strategic business consulting and planning', 'Consulting', 3000.00),
('E-commerce Solutions', 'Online store development and management', 'Development', 3500.00),
('UI/UX Design', 'User interface and experience design', 'Design', 2000.00);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('company_name', 'CRM Pro', 'string', 'Company name displayed in the application'),
('company_email', 'hello@crmPro.com', 'string', 'Default company email address'),
('company_phone', '+1 (555) 123-4567', 'string', 'Company phone number'),
('default_currency', 'USD', 'string', 'Default currency for pricing'),
('leads_per_page', '25', 'number', 'Number of leads to display per page'),
('auto_assign_leads', 'false', 'boolean', 'Automatically assign new leads to users'),
('email_notifications', 'true', 'boolean', 'Enable email notifications for new leads');

-- Create views for reporting
CREATE VIEW lead_summary AS
SELECT 
    l.id,
    l.name,
    l.email,
    l.company,
    l.status,
    l.source,
    l.estimated_value,
    l.created_at,
    u1.full_name as assigned_to_name,
    u2.full_name as created_by_name,
    COUNT(la.id) as activity_count
FROM leads l
LEFT JOIN users u1 ON l.assigned_to = u1.id
LEFT JOIN users u2 ON l.created_by = u2.id
LEFT JOIN lead_activities la ON l.id = la.lead_id
GROUP BY l.id;

-- Create view for dashboard statistics
CREATE VIEW dashboard_stats AS
SELECT 
    COUNT(*) as total_leads,
    COUNT(CASE WHEN status = 'new' THEN 1 END) as new_leads,
    COUNT(CASE WHEN status = 'hot' THEN 1 END) as hot_leads,
    COUNT(CASE WHEN status = 'converted' THEN 1 END) as converted_leads,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as leads_this_week,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as leads_this_month,
    SUM(CASE WHEN status = 'converted' THEN estimated_value ELSE 0 END) as total_revenue,
    AVG(estimated_value) as avg_lead_value
FROM leads;
