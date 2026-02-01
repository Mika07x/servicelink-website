-- ServiceLink Database Schema
CREATE DATABASE IF NOT EXISTS servicelink_db;
USE servicelink_db;

-- Campuses table
CREATE TABLE campuses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(10) UNIQUE NOT NULL,
    location VARCHAR(255) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code)
);

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_number VARCHAR(20) UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone_number VARCHAR(20),
    password_hash VARCHAR(255),
    google_id VARCHAR(255) UNIQUE,
    role ENUM('admin', 'department_admin', 'staff', 'user') DEFAULT 'user',
    department_id INT,
    campus_id INT,
    year_level ENUM('1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year', 'Graduate', 'Faculty', 'Staff') DEFAULT '1st Year',
    profile_picture VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_user_number (user_number),
    INDEX idx_role (role),
    INDEX idx_department (department_id),
    INDEX idx_campus (campus_id)
);

-- Departments table
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(10) UNIQUE NOT NULL,
    description TEXT,
    head_user_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_head (head_user_id)
);

-- Service categories table
CREATE TABLE service_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    department_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_department (department_id)
);

-- Tickets table
CREATE TABLE tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_number VARCHAR(20) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category_id INT,
    priority ENUM('low', 'medium', 'high', 'emergency') DEFAULT 'low',
    status ENUM('open', 'in_progress', 'resolved', 'closed', 'cancelled') DEFAULT 'open',
    requester_id INT NOT NULL,
    assigned_to INT,
    department_id INT,
    ai_analysis TEXT,
    resolution TEXT,
    satisfaction_rating INT CHECK (satisfaction_rating >= 1 AND satisfaction_rating <= 5),
    satisfaction_feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_ticket_number (ticket_number),
    INDEX idx_requester (requester_id),
    INDEX idx_assigned (assigned_to),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_department (department_id),
    INDEX idx_created (created_at)
);

-- Ticket attachments table
CREATE TABLE ticket_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100),
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ticket (ticket_id),
    INDEX idx_uploader (uploaded_by)
);

-- Ticket comments/chat table
CREATE TABLE ticket_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ticket (ticket_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
);

-- Ticket status history table
CREATE TABLE ticket_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    old_status ENUM('open', 'in_progress', 'resolved', 'closed', 'cancelled'),
    new_status ENUM('open', 'in_progress', 'resolved', 'closed', 'cancelled') NOT NULL,
    changed_by INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ticket (ticket_id),
    INDEX idx_changed_by (changed_by)
);

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    ticket_id INT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('ticket_created', 'ticket_updated', 'ticket_assigned', 'comment_added', 'status_changed', 'system') DEFAULT 'system',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_ticket (ticket_id),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
);

-- System settings table
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
);

-- User sessions table (for better session management)
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_activity (last_activity)
);

-- Password reset tokens table
CREATE TABLE password_reset_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at)
);

-- Add foreign key constraints
ALTER TABLE users ADD FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;
ALTER TABLE users ADD FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE SET NULL;
ALTER TABLE departments ADD FOREIGN KEY (head_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Insert default campuses
INSERT INTO campuses (name, code, location, address, phone, email) VALUES
('South Campus', 'SOUTH', 'South District', '123 South Avenue, South District', '+1-234-567-8901', 'south@servicelink.edu'),
('North Campus', 'NORTH', 'North District', '456 North Street, North District', '+1-234-567-8902', 'north@servicelink.edu'),
('Congressional Campus', 'CONG', 'Congressional District', '789 Congressional Road, Congressional District', '+1-234-567-8903', 'congressional@servicelink.edu');

-- Insert default departments
INSERT INTO departments (name, code, description) VALUES
('Information Technology', 'IT', 'IT support and technical services'),
('Facilities Management', 'FM', 'Building and facility maintenance'),
('Academic Affairs', 'AA', 'Academic support services (non-grade related)'),
('Student Affairs', 'SA', 'Student support and welfare services'),
('Human Resources', 'HR', 'HR and personnel services'),
('Library Services', 'LIB', 'Library and research support'),
('Security', 'SEC', 'Campus security and safety'),
('Transportation', 'TRANS', 'Campus transportation services');

-- Insert default service categories
INSERT INTO service_categories (name, description, department_id) VALUES
-- IT Department
('Computer/Laptop Issues', 'Hardware and software problems', 1),
('Network/Internet Problems', 'Connectivity and network issues', 1),
('Email/System Access', 'Account and access related issues', 1),
('Software Installation', 'Software installation and licensing', 1),
('Printer/Scanner Issues', 'Printing and scanning problems', 1),

-- Facilities Management
('Classroom Maintenance', 'Classroom repairs and maintenance', 2),
('Electrical Issues', 'Electrical problems and repairs', 2),
('Plumbing Issues', 'Water and plumbing problems', 2),
('Air Conditioning', 'HVAC and cooling issues', 2),
('Furniture/Equipment', 'Furniture and equipment requests', 2),

-- Academic Affairs
('Course Information', 'Non-grade related course inquiries', 3),
('Schedule Conflicts', 'Class scheduling issues', 3),
('Academic Resources', 'Learning materials and resources', 3),
('Examination Issues', 'Non-grade related exam concerns', 3),

-- Student Affairs
('Student Activities', 'Events and activities support', 4),
('Counseling Services', 'Student counseling and support', 4),
('Health Services', 'Medical and health concerns', 4),
('Scholarship Inquiries', 'Scholarship information and support', 4),

-- Library Services
('Book/Resource Requests', 'Library material requests', 6),
('Research Assistance', 'Research and reference support', 6),
('Library Access Issues', 'Library system and access problems', 6),

-- Security
('Lost and Found', 'Lost item reports and inquiries', 7),
('Security Concerns', 'Safety and security issues', 7),
('ID Card Issues', 'Student/Staff ID problems', 7),

-- Transportation
('Shuttle Service', 'Campus shuttle inquiries', 8),
('Parking Issues', 'Parking permits and problems', 8);

-- Insert default admin user (password: admin123)
INSERT INTO users (user_number, first_name, last_name, email, password_hash, role, department_id, year_level, is_active, email_verified) VALUES
('ADMIN001', 'System', 'Administrator', 'admin@servicelink.com', '$2y$10$8K1p/wgyQ1uIiWi5Cp6VdeqQwerzXIaTr/SdWklaEKlfQvjgZomjG', 'admin', 1, 'Staff', TRUE, TRUE);

-- Insert department heads and staff (password: password123)
INSERT INTO users (user_number, first_name, last_name, email, password_hash, role, department_id, year_level, is_active, email_verified) VALUES
-- IT Department Head and Staff
('DEPT001', 'John', 'Smith', 'john.smith@servicelink.com', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'department_admin', 1, 'Staff', TRUE, TRUE),
('STAFF001', 'Alice', 'Johnson', 'alice.johnson@servicelink.com', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'staff', 1, 'Staff', TRUE, TRUE),
('STAFF002', 'Bob', 'Wilson', 'bob.wilson@servicelink.com', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'staff', 1, 'Staff', TRUE, TRUE),

-- Facilities Management Head and Staff
('DEPT002', 'Maria', 'Garcia', 'maria.garcia@servicelink.com', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'department_admin', 2, 'Staff', TRUE, TRUE),
('STAFF003', 'David', 'Brown', 'david.brown@servicelink.com', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'staff', 2, 'Staff', TRUE, TRUE),

-- Academic Affairs Head
('DEPT003', 'Sarah', 'Davis', 'sarah.davis@servicelink.com', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'department_admin', 3, 'Faculty', TRUE, TRUE),
('STAFF004', 'Michael', 'Miller', 'michael.miller@servicelink.com', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'staff', 3, 'Faculty', TRUE, TRUE),

-- Student Affairs Head
('DEPT004', 'Jennifer', 'Taylor', 'jennifer.taylor@servicelink.com', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'department_admin', 4, 'Staff', TRUE, TRUE),
('STAFF005', 'Robert', 'Anderson', 'robert.anderson@servicelink.com', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'staff', 4, 'Staff', TRUE, TRUE),

-- HR Head
('DEPT005', 'Lisa', 'Thomas', 'lisa.thomas@servicelink.com', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'department_admin', 5, 'Staff', TRUE, TRUE),

-- Library Services Head
('DEPT006', 'James', 'Jackson', 'james.jackson@servicelink.com', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'department_admin', 6, 'Faculty', TRUE, TRUE),
('STAFF006', 'Emily', 'White', 'emily.white@servicelink.com', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'staff', 6, 'Staff', TRUE, TRUE),

-- Security Head
('DEPT007', 'William', 'Harris', 'william.harris@servicelink.com', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'department_admin', 7, 'Staff', TRUE, TRUE),

-- Transportation Head
('DEPT008', 'Patricia', 'Martin', 'patricia.martin@servicelink.com', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'department_admin', 8, 'Staff', TRUE, TRUE),

-- Sample regular users (students)
('STU2024001', 'Emma', 'Rodriguez', 'emma.rodriguez@student.edu', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'user', NULL, '3rd Year', TRUE, TRUE),
('STU2024002', 'Daniel', 'Lee', 'daniel.lee@student.edu', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'user', NULL, '2nd Year', TRUE, TRUE),
('STU2024003', 'Sophia', 'Clark', 'sophia.clark@student.edu', '$2y$10$E4YW2vF5jyO4XzLJAqHCm.1rEzHGQ0rY7HIgp0h6wmRaq2/1bgJ9W', 'user', NULL, '4th Year', TRUE, TRUE);

-- Update departments to assign heads
UPDATE departments SET head_user_id = 2 WHERE id = 1; -- IT Department - John Smith
UPDATE departments SET head_user_id = 5 WHERE id = 2; -- Facilities - Maria Garcia  
UPDATE departments SET head_user_id = 7 WHERE id = 3; -- Academic Affairs - Sarah Davis
UPDATE departments SET head_user_id = 9 WHERE id = 4; -- Student Affairs - Jennifer Taylor
UPDATE departments SET head_user_id = 11 WHERE id = 5; -- HR - Lisa Thomas
UPDATE departments SET head_user_id = 12 WHERE id = 6; -- Library - James Jackson
UPDATE departments SET head_user_id = 14 WHERE id = 7; -- Security - William Harris
UPDATE departments SET head_user_id = 15 WHERE id = 8; -- Transportation - Patricia Martin

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('site_name', 'ServiceLink', 'Website name'),
('site_description', 'University Service Ticketing System', 'Website description'),
('max_file_size', '10485760', 'Maximum file upload size in bytes (10MB)'),
('allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt', 'Allowed file extensions'),
('ticket_auto_close_days', '30', 'Days after which resolved tickets are auto-closed'),
('email_notifications', '1', 'Enable email notifications'),
('ai_ticket_routing', '1', 'Enable AI-powered ticket routing'),
('maintenance_mode', '0', 'Enable maintenance mode');

-- Create indexes for better performance
CREATE INDEX idx_tickets_composite ON tickets(status, priority, created_at);
CREATE INDEX idx_comments_composite ON ticket_comments(ticket_id, created_at);
CREATE INDEX idx_notifications_composite ON notifications(user_id, is_read, created_at);

-- Create views for reporting
CREATE VIEW ticket_summary AS
SELECT 
    t.id,
    t.ticket_number,
    t.title,
    t.status,
    t.priority,
    t.created_at,
    t.resolved_at,
    t.closed_at,
    CONCAT(u.first_name, ' ', u.last_name) as requester_name,
    u.email as requester_email,
    u.department_id as requester_department,
    d.name as department_name,
    sc.name as category_name,
    CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name,
    TIMESTAMPDIFF(HOUR, t.created_at, COALESCE(t.resolved_at, NOW())) as resolution_time_hours
FROM tickets t
LEFT JOIN users u ON t.requester_id = u.id
LEFT JOIN users staff ON t.assigned_to = staff.id
LEFT JOIN departments d ON t.department_id = d.id
LEFT JOIN service_categories sc ON t.category_id = sc.id;

-- Create triggers for automatic updates
DELIMITER //

CREATE TRIGGER update_ticket_resolved_time
BEFORE UPDATE ON tickets
FOR EACH ROW
BEGIN
    IF NEW.status = 'resolved' AND OLD.status != 'resolved' THEN
        SET NEW.resolved_at = NOW();
    END IF;
    
    IF NEW.status = 'closed' AND OLD.status != 'closed' THEN
        SET NEW.closed_at = NOW();
    END IF;
END//

CREATE TRIGGER log_ticket_status_change
AFTER UPDATE ON tickets
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO ticket_status_history (ticket_id, old_status, new_status, changed_by, created_at)
        VALUES (NEW.id, OLD.status, NEW.status, NEW.assigned_to, NOW());
    END IF;
END//

DELIMITER ;

-- Create stored procedures for common operations
DELIMITER //

CREATE PROCEDURE GetTicketStats(IN dept_id INT, IN date_from DATE, IN date_to DATE)
BEGIN
    SELECT 
        COUNT(*) as total_tickets,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_tickets,
        AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) as avg_resolution_time,
        SUM(CASE WHEN priority = 'emergency' THEN 1 ELSE 0 END) as emergency_tickets,
        SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority_tickets
    FROM tickets 
    WHERE (dept_id IS NULL OR department_id = dept_id)
    AND DATE(created_at) BETWEEN date_from AND date_to;
END//

DELIMITER ;