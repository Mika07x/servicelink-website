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

-- Service subcategories table
CREATE TABLE service_subcategories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id)
);

-- Locations table
CREATE TABLE locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description VARCHAR(500),
    building VARCHAR(100),
    floor VARCHAR(50),
    room VARCHAR(50),
    campus_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE SET NULL,
    INDEX idx_campus (campus_id),
    INDEX idx_building (building)
);

-- Tickets table
CREATE TABLE tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_number VARCHAR(20) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category_id INT,
    subcategory_id INT,
    location_id INT,
    priority ENUM('low', 'medium', 'high', 'emergency') DEFAULT 'low',
    status ENUM('new', 'pending', 'assigned', 'in_progress', 'on_hold', 'resolved', 'closed', 'reopen') DEFAULT 'new',
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
    FOREIGN KEY (subcategory_id) REFERENCES service_subcategories(id) ON DELETE SET NULL,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_ticket_number (ticket_number),
    INDEX idx_requester (requester_id),
    INDEX idx_assigned (assigned_to),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_department (department_id),
    INDEX idx_subcategory (subcategory_id),
    INDEX idx_location (location_id),
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
    attachment_type ENUM('image', 'video', 'document', 'other') DEFAULT 'other',
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ticket (ticket_id),
    INDEX idx_uploader (uploaded_by),
    INDEX idx_attachment_type (attachment_type)
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
    old_status ENUM('new', 'pending', 'assigned', 'in_progress', 'on_hold', 'resolved', 'closed', 'reopen'),
    new_status ENUM('new', 'pending', 'assigned', 'in_progress', 'on_hold', 'resolved', 'closed', 'reopen') NOT NULL,
    changed_by INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ticket (ticket_id),
    INDEX idx_changed_by (changed_by)
);

-- Ticket history table for tracking all changes
CREATE TABLE ticket_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    changed_by INT NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    change_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ticket_history_ticket (ticket_id),
    INDEX idx_ticket_history_date (created_at)
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
-- IT Department (1)
('Computer/Laptop Issues', 'Hardware and software problems with computers and laptops', 1),
('Network/Internet Problems', 'Connectivity and network access issues', 1),
('Email/System Access', 'Account access and email related issues', 1),
('Software Installation/Updates', 'Software installation, updates and licensing', 1),
('Printer/Scanner Issues', 'Printing, scanning and peripheral device problems', 1),
('Audio/Visual Equipment', 'Projectors, speakers, microphones and AV equipment', 1),
('Website/Online Services', 'University website and online platform issues', 1),

-- Facilities Management (2)
('Classroom/Laboratory Issues', 'Classroom and laboratory maintenance and repairs', 2),
('Electrical Problems', 'Electrical issues, outlets, and lighting problems', 2),
('Plumbing/Water Issues', 'Water, plumbing, and restroom facility problems', 2),
('Air Conditioning/Ventilation', 'HVAC, cooling, heating and air quality issues', 2),
('Furniture/Equipment Repair', 'Furniture damage and equipment repair requests', 2),
('Building Maintenance', 'General building maintenance and structural issues', 2),
('Cleaning/Sanitation', 'Cleaning services and sanitation concerns', 2),

-- Academic Affairs (3)
('Course/Schedule Information', 'Non-grade related course and schedule inquiries', 3),
('Academic Resources', 'Learning materials, textbooks and academic resources', 3),
('Examination Support', 'Non-grade related exam scheduling and support', 3),
('Academic Events', 'Seminars, conferences and academic event support', 3),
('Research Support', 'Research facilities and academic research assistance', 3),
('Academic Records', 'Transcript requests and academic documentation', 3),

-- Student Affairs (4)
('Student Activities/Events', 'Student organizations and campus events support', 4),
('Counseling/Wellness Services', 'Student counseling and mental health support', 4),
('Health Services', 'Medical services and health-related concerns', 4),
('Scholarship/Financial Aid', 'Scholarship information and financial assistance', 4),
('Student Housing', 'Dormitory and student accommodation issues', 4),
('Student ID/Cards', 'Student identification and access card issues', 4),
('Disciplinary/Conduct', 'Student conduct and disciplinary matters', 4),

-- Human Resources (5)
('Employment/Job Inquiries', 'Job applications and employment information', 5),
('Benefits/Compensation', 'Employee benefits and compensation inquiries', 5),
('Training/Development', 'Staff training and professional development', 5),
('HR Policies/Procedures', 'Human resources policies and procedure questions', 5),
('Payroll Issues', 'Salary, payroll and compensation problems', 5),

-- Library Services (6)
('Book/Resource Requests', 'Library material requests and acquisitions', 6),
('Research Assistance', 'Research support and reference services', 6),
('Library Access/Systems', 'Library system access and technical issues', 6),
('Study Spaces/Facilities', 'Study rooms, facilities and equipment in library', 6),
('Digital Resources', 'Online databases and digital library resources', 6),
('Library Events/Programs', 'Library workshops, events and educational programs', 6),

-- Security (7)
('Lost and Found', 'Lost item reports and found item inquiries', 7),
('Security/Safety Concerns', 'Campus safety and security incident reports', 7),
('Access Control', 'Building access, key cards and entry issues', 7),
('Emergency Response', 'Emergency situations and response procedures', 7),
('Parking/Traffic', 'Parking violations, permits and traffic issues', 7),
('Incident Reports', 'Security incidents and violation reports', 7),

-- Transportation (8)
('Shuttle Service', 'Campus shuttle schedules and service issues', 8),
('Parking Services', 'Parking permits, spaces and related problems', 8),
('Vehicle Registration', 'Campus vehicle registration and permits', 8),
('Transportation Events', 'Special event transportation and logistics', 8);

-- Insert service subcategories
INSERT INTO service_subcategories (name, description, category_id) VALUES
-- IT Department subcategories
-- Computer/Laptop Issues (1)
('Hardware Malfunction', 'Physical computer/laptop hardware problems', 1),
('Software Crashes/Errors', 'Application crashes and software errors', 1),
('Slow Performance', 'Computer running slowly or freezing', 1),
('Blue Screen/System Crashes', 'System crashes and critical errors', 1),
('Virus/Malware Issues', 'Computer infected with virus or malware', 1),

-- Network/Internet Problems (2)
('WiFi Connection Issues', 'Cannot connect to wireless network', 2),
('Ethernet/Wired Connection', 'Wired network connection problems', 2),
('Slow Internet Speed', 'Internet connection is slow', 2),
('Network Access Denied', 'Cannot access network resources or websites', 2),
('VPN Connection Issues', 'Problems connecting to university VPN', 2),

-- Email/System Access (3)
('Password Reset', 'Forgot password or account locked', 3),
('New Account Creation', 'Need new system account access', 3),
('Permission/Access Issues', 'Cannot access certain systems or files', 3),
('Email Not Working', 'Email sending/receiving problems', 3),
('Two-Factor Authentication', 'Issues with 2FA setup or access', 3),

-- Software Installation/Updates (4)
('New Software Installation', 'Need new software installed', 4),
('Software Updates', 'Update existing software to newer version', 4),
('License/Activation Issues', 'Software licensing and activation problems', 4),
('Software Compatibility', 'Software not compatible with system', 4),
('Antivirus/Security Software', 'Antivirus and security software issues', 4),

-- Printer/Scanner Issues (5)
('Cannot Print', 'Printer not responding or printing', 5),
('Print Quality Problems', 'Poor print quality, faded or blurry prints', 5),
('Scanner Not Working', 'Scanner not functioning properly', 5),
('Paper Jams', 'Printer paper jam issues', 5),
('Printer Driver Issues', 'Printer driver installation or update problems', 5),

-- Audio/Visual Equipment (6)
('Projector Problems', 'Projector not working or displaying properly', 6),
('Audio System Issues', 'Speakers, microphones not working', 6),
('Screen/Display Problems', 'Monitor or display screen issues', 6),
('Cable/Connection Issues', 'HDMI, VGA or other cable connection problems', 6),
('Remote Control Issues', 'AV equipment remote controls not working', 6),

-- Website/Online Services (7)
('University Website Issues', 'Problems accessing university website', 7),
('Online Portal Access', 'Cannot access student/staff online portals', 7),
('Online Learning Platform', 'Issues with LMS or online learning systems', 7),
('Online Registration', 'Problems with online course registration', 7),
('Digital Services', 'Other online university services not working', 7),

-- Facilities Management subcategories
-- Classroom/Laboratory Issues (8)
('Classroom Equipment', 'Desks, chairs, whiteboards not working', 8),
('Laboratory Equipment', 'Lab equipment malfunction or damage', 8),
('Classroom Lighting', 'Lights not working or too dim/bright', 8),
('Classroom Temperature', 'Room too hot, cold, or poor ventilation', 8),
('Classroom Cleanliness', 'Dirty or unsanitary classroom conditions', 8),

-- Electrical Problems (9)
('Power Outage', 'No electricity in area', 9),
('Electrical Outlets', 'Power outlets not working', 9),
('Light Bulbs/Fixtures', 'Light bulbs burned out or fixtures broken', 9),
('Electrical Safety Hazards', 'Exposed wires or electrical dangers', 9),
('Generator Issues', 'Backup generator problems', 9),

-- Plumbing/Water Issues (10)
('Leaky Faucets/Pipes', 'Water leaking from taps or pipes', 10),
('Toilet Problems', 'Toilets not flushing or clogged', 10),
('Low Water Pressure', 'Weak water flow from taps', 10),
('Clogged Drains', 'Sinks or floor drains blocked', 10),
('Water Quality Issues', 'Dirty, discolored, or bad-tasting water', 10),

-- Air Conditioning/Ventilation (11)
('AC Not Cooling', 'Air conditioning not working or not cold', 11),
('AC Too Cold/Hot', 'Temperature control issues', 11),
('Poor Air Circulation', 'Stuffy air or poor ventilation', 11),
('AC Noise Issues', 'Air conditioning making loud noises', 11),
('Air Quality Problems', 'Bad air quality or strange odors', 11),

-- Furniture/Equipment Repair (12)
('Broken Chairs/Tables', 'Damaged classroom or office furniture', 12),
('Door/Window Issues', 'Doors or windows not opening/closing properly', 12),
('Cabinet/Storage Issues', 'Broken cabinets or storage units', 12),
('Equipment Installation', 'Need furniture or equipment installed', 12),
('Equipment Replacement', 'Need damaged equipment replaced', 12),

-- Building Maintenance (13)
('Roof/Ceiling Issues', 'Leaks, cracks, or ceiling problems', 13),
('Floor/Flooring Problems', 'Damaged, loose, or unsafe flooring', 13),
('Wall/Paint Issues', 'Cracks, holes, or paint problems on walls', 13),
('Structural Problems', 'Building structural issues or safety concerns', 13),
('Exterior Maintenance', 'Building exterior, landscaping, or grounds issues', 13),

-- Cleaning/Sanitation (14)
('Regular Cleaning', 'Request for regular cleaning services', 14),
('Deep Cleaning', 'Need thorough or specialized cleaning', 14),
('Waste Management', 'Trash collection or disposal issues', 14),
('Restroom Sanitation', 'Restroom cleaning and sanitation issues', 14),
('Pest Control', 'Insects, rodents, or pest problems', 14),

-- Academic Affairs subcategories
-- Course/Schedule Information (15)
('Course Prerequisites', 'Questions about course requirements', 15),
('Class Schedule Changes', 'Schedule modifications or conflicts', 15),
('Course Availability', 'Course offering and availability inquiries', 15),
('Academic Calendar', 'Questions about academic dates and deadlines', 15),
('Course Descriptions', 'Information about course content and objectives', 15),

-- Academic Resources (16)
('Textbook/Materials', 'Required textbooks and learning materials', 16),
('Library Resources', 'Academic books and research materials', 16),
('Online Learning Tools', 'Digital learning platforms and tools', 16),
('Study Materials', 'Additional study resources and materials', 16),
('Academic Software', 'Specialized academic software access', 16),

-- Examination Support (17)
('Exam Scheduling', 'Exam dates and scheduling information', 17),
('Exam Locations', 'Exam venue and room assignments', 17),
('Special Accommodations', 'Disability or special needs exam support', 17),
('Make-up Exams', 'Missed exam rescheduling', 17),
('Exam Procedures', 'Questions about exam rules and procedures', 17),

-- Academic Events (18)
('Seminars/Workshops', 'Academic seminars and workshop information', 18),
('Conferences', 'Academic conferences and symposiums', 18),
('Guest Lectures', 'Special lectures and guest speaker events', 18),
('Academic Competitions', 'Academic contests and competitions', 18),
('Graduation Events', 'Graduation ceremonies and related events', 18),

-- Research Support (19)
('Research Facilities', 'Access to research labs and facilities', 19),
('Research Funding', 'Research grants and funding opportunities', 19),
('Research Ethics', 'Research ethics approval and guidelines', 19),
('Research Equipment', 'Specialized research equipment access', 19),
('Publication Support', 'Academic publication and journal support', 19),

-- Academic Records (20)
('Transcript Requests', 'Official transcript requests', 20),
('Grade Reports', 'Academic grade and progress reports', 20),
('Enrollment Verification', 'Proof of enrollment documentation', 20),
('Academic Certificates', 'Academic achievement certificates', 20),
('Record Corrections', 'Corrections to academic records', 20),

-- Student Affairs subcategories
-- Student Activities/Events (21)
('Student Organizations', 'Student clubs and organization support', 21),
('Campus Events', 'Campus-wide events and activities', 21),
('Cultural Activities', 'Cultural events and celebrations', 21),
('Sports/Recreation', 'Sports events and recreational activities', 21),
('Leadership Programs', 'Student leadership development programs', 21),

-- Counseling/Wellness Services (22)
('Personal Counseling', 'Individual counseling and mental health support', 22),
('Academic Counseling', 'Academic guidance and career counseling', 22),
('Crisis Support', 'Emergency mental health and crisis intervention', 22),
('Group Counseling', 'Group therapy and support sessions', 22),
('Wellness Programs', 'Mental health and wellness programs', 22),

-- Health Services (23)
('Medical Consultation', 'General medical consultation and checkups', 23),
('Emergency Medical', 'Medical emergencies and urgent care', 23),
('Health Insurance', 'Student health insurance inquiries', 23),
('Vaccination/Immunization', 'Required vaccinations and health clearances', 23),
('Health Education', 'Health awareness and education programs', 23),

-- Scholarship/Financial Aid (24)
('Scholarship Applications', 'Scholarship application process and requirements', 24),
('Financial Aid', 'Student financial assistance programs', 24),
('Payment Plans', 'Tuition payment plans and options', 24),
('Work-Study Programs', 'Student work opportunities and programs', 24),
('Emergency Financial Aid', 'Emergency financial assistance for students', 24),

-- Student Housing (25)
('Dormitory Assignment', 'Room assignments and housing applications', 25),
('Housing Maintenance', 'Dormitory maintenance and repair issues', 25),
('Roommate Issues', 'Roommate conflicts and room changes', 25),
('Housing Policies', 'Dormitory rules and housing policies', 25),
('Move-in/Move-out', 'Housing check-in and check-out procedures', 25),

-- Student ID/Cards (26)
('New ID Card', 'New student ID card issuance', 26),
('Lost/Stolen ID', 'Replacement for lost or stolen ID cards', 26),
('ID Card Problems', 'ID card not working or damaged', 26),
('Access Card Issues', 'Building or facility access card problems', 26),
('ID Photo Update', 'Update photo on student ID card', 26),

-- Disciplinary/Conduct (27)
('Code of Conduct', 'Student code of conduct inquiries', 27),
('Disciplinary Procedures', 'Disciplinary process and procedures', 27),
('Appeals Process', 'Academic or disciplinary appeals', 27),
('Behavioral Issues', 'Student behavior concerns and reports', 27),
('Academic Integrity', 'Academic honesty and integrity matters', 27),

-- Human Resources subcategories
-- Employment/Job Inquiries (28)
('Job Applications', 'Employment application process', 28),
('Job Openings', 'Available positions and job postings', 28),
('Interview Process', 'Job interview scheduling and procedures', 28),
('Employment Verification', 'Employment verification and references', 28),
('Internship Programs', 'Student and graduate internship opportunities', 28),

-- Benefits/Compensation (29)
('Health Benefits', 'Employee health insurance and benefits', 29),
('Retirement Plans', 'Employee retirement and pension plans', 29),
('Leave Policies', 'Vacation, sick leave, and time-off policies', 29),
('Employee Discounts', 'Staff discounts and perks', 29),
('Compensation Review', 'Salary review and compensation inquiries', 29),

-- Training/Development (30)
('Professional Development', 'Staff training and skill development programs', 30),
('Orientation Programs', 'New employee orientation and onboarding', 30),
('Certification Programs', 'Professional certification and continuing education', 30),
('Workshop/Seminars', 'Staff workshops and training seminars', 30),
('Performance Reviews', 'Employee performance evaluation process', 30),

-- HR Policies/Procedures (31)
('Employee Handbook', 'Employee policies and handbook inquiries', 31),
('Workplace Policies', 'Workplace rules and procedure questions', 31),
('Grievance Procedures', 'Employee complaint and grievance process', 31),
('Equal Opportunity', 'Equal employment opportunity and diversity', 31),
('Workplace Safety', 'Employee safety policies and procedures', 31),

-- Payroll Issues (32)
('Salary Problems', 'Payroll errors and salary issues', 32),
('Tax Withholding', 'Tax deduction and withholding questions', 32),
('Direct Deposit', 'Payroll direct deposit setup and issues', 32),
('Overtime Pay', 'Overtime compensation and policies', 32),
('Pay Stub Issues', 'Payroll statement and documentation problems', 32),

-- Library Services subcategories
-- Book/Resource Requests (33)
('Book Reservations', 'Reserve books and library materials', 33),
('Interlibrary Loans', 'Request materials from other libraries', 33),
('New Acquisitions', 'Suggest new books and materials for purchase', 33),
('Book Renewals', 'Extend borrowing period for library materials', 33),
('Lost/Damaged Books', 'Report lost or damaged library materials', 33),

-- Research Assistance (34)
('Reference Services', 'Research help and reference questions', 34),
('Database Access', 'Access to academic databases and journals', 34),
('Citation Help', 'Assistance with citations and bibliography', 34),
('Research Strategies', 'Help developing research methodologies', 34),
('Subject Guides', 'Subject-specific research guides and resources', 34),

-- Library Access/Systems (35)
('Library Card Issues', 'Library card problems and renewals', 35),
('Computer/Internet Access', 'Library computer and internet access', 35),
('Printing/Copying', 'Library printing and photocopying services', 35),
('System Login Problems', 'Library system access and login issues', 35),
('Mobile App Issues', 'Library mobile app problems', 35),

-- Study Spaces/Facilities (36)
('Study Room Reservations', 'Reserve group study rooms', 36),
('Quiet Study Areas', 'Issues with noise in study areas', 36),
('Equipment Checkout', 'Borrow laptops, calculators, and equipment', 36),
('Facility Problems', 'Library facility maintenance issues', 36),
('Accessibility Services', 'Disability access and accommodation', 36),

-- Digital Resources (37)
('E-book Access', 'Electronic book access and downloading', 37),
('Online Journals', 'Access to online academic journals', 37),
('Digital Archives', 'Historical documents and digital collections', 37),
('Multimedia Resources', 'Videos, audio, and multimedia materials', 37),
('Software Access', 'Specialized software available in library', 37),

-- Library Events/Programs (38)
('Information Literacy', 'Library skills and information literacy training', 38),
('Workshops', 'Library workshops and training sessions', 38),
('Book Clubs', 'Library book clubs and reading programs', 38),
('Author Events', 'Author visits and literary events', 38),
('Exhibitions', 'Library exhibitions and displays', 38),

-- Security subcategories
-- Lost and Found (39)
('Lost Items', 'Report lost personal belongings', 39),
('Found Items', 'Turn in found items or claim found property', 39),
('Lost ID/Keys', 'Lost identification cards or keys', 39),
('Lost Electronics', 'Lost phones, laptops, or electronic devices', 39),
('Lost Documents', 'Lost important documents or papers', 39),

-- Security/Safety Concerns (40)
('Suspicious Activity', 'Report suspicious behavior or activities', 40),
('Safety Hazards', 'Report safety hazards or dangerous conditions', 40),
('Theft/Vandalism', 'Report theft, vandalism, or property damage', 40),
('Personal Safety', 'Personal safety concerns and escort services', 40),
('Emergency Situations', 'Report emergencies or urgent safety issues', 40),

-- Access Control (41)
('Building Access', 'Problems accessing buildings or facilities', 41),
('Key Card Issues', 'Access card not working or needs replacement', 41),
('Lock/Key Problems', 'Broken locks or key issues', 41),
('After-Hours Access', 'Special access requests for after hours', 41),
('Visitor Access', 'Guest access and visitor registration', 41),

-- Emergency Response (42)
('Fire Safety', 'Fire alarms, extinguishers, and fire safety', 42),
('Medical Emergencies', 'Medical emergency response and first aid', 42),
('Natural Disasters', 'Weather emergencies and natural disaster response', 42),
('Evacuation Procedures', 'Emergency evacuation plans and procedures', 42),
('Emergency Communication', 'Emergency alert systems and communication', 42),

-- Parking/Traffic (43)
('Parking Violations', 'Parking tickets and violation appeals', 43),
('Parking Permits', 'Parking permit applications and renewals', 43),
('Parking Availability', 'Parking space availability and assignments', 43),
('Traffic Issues', 'Campus traffic flow and safety concerns', 43),
('Vehicle Registration', 'Campus vehicle registration requirements', 43),

-- Incident Reports (44)
('Accident Reports', 'Report accidents and injuries on campus', 44),
('Property Damage', 'Report damage to university property', 44),
('Behavioral Incidents', 'Report disruptive or inappropriate behavior', 44),
('Policy Violations', 'Report violations of university policies', 44),
('Witness Statements', 'Provide witness information for incidents', 44),

-- Transportation subcategories
-- Shuttle Service (45)
('Shuttle Schedule', 'Shuttle bus schedules and route information', 45),
('Shuttle Delays', 'Report shuttle delays or service interruptions', 45),
('Route Changes', 'Information about route modifications', 45),
('Shuttle Accessibility', 'Wheelchair accessible shuttle services', 45),
('Lost Items on Shuttle', 'Items left behind on shuttle buses', 45),

-- Parking Services (46)
('Parking Permits', 'Apply for or renew parking permits', 46),
('Parking Enforcement', 'Parking violation tickets and appeals', 46),
('Parking Maintenance', 'Parking lot maintenance and repair issues', 46),
('Reserved Parking', 'Special parking space requests and assignments', 46),
('Parking Information', 'General parking rules and information', 46),

-- Vehicle Registration (47)
('Campus Vehicle Registration', 'Register vehicles for campus access', 47),
('Registration Renewal', 'Renew vehicle registration permits', 47),
('Registration Changes', 'Update vehicle registration information', 47),
('Temporary Permits', 'Short-term vehicle access permits', 47),
('Registration Problems', 'Issues with vehicle registration process', 47),

-- Transportation Events (48)
('Event Transportation', 'Special transportation for campus events', 48),
('Group Transportation', 'Charter bus or group transportation requests', 48),
('Field Trip Transportation', 'Transportation for academic field trips', 48),
('Emergency Transportation', 'Emergency or urgent transportation needs', 48),
('Transportation Coordination', 'Coordinate transportation for large groups', 48);

-- Insert locations
INSERT INTO locations (name, description, building, floor, room, campus_id) VALUES
-- South Campus locations
('CL1 Ground Floor', 'Computer Laboratory 1 - Ground Floor', 'Computer Building', 'Ground Floor', 'CL1', 1),
('CL1 2nd Floor', 'Computer Laboratory 1 - 2nd Floor', 'Computer Building', '2nd Floor', 'CL1-2F', 1),
('CL1 3rd Floor', 'Computer Laboratory 1 - 3rd Floor', 'Computer Building', '3rd Floor', 'CL1-3F', 1),
('CL2 Ground Floor', 'Computer Laboratory 2 - Ground Floor', 'Computer Building', 'Ground Floor', 'CL2', 1),
('CL2 2nd Floor', 'Computer Laboratory 2 - 2nd Floor', 'Computer Building', '2nd Floor', 'CL2-2F', 1),
('Library Ground Floor', 'Main Library - Ground Floor', 'Library Building', 'Ground Floor', 'LIB-GF', 1),
('Library 2nd Floor', 'Main Library - 2nd Floor', 'Library Building', '2nd Floor', 'LIB-2F', 1),
('Library 3rd Floor', 'Main Library - 3rd Floor', 'Library Building', '3rd Floor', 'LIB-3F', 1),
('Admin Building 1st Floor', 'Administration Building - 1st Floor', 'Admin Building', '1st Floor', 'ADMIN-1F', 1),
('Admin Building 2nd Floor', 'Administration Building - 2nd Floor', 'Admin Building', '2nd Floor', 'ADMIN-2F', 1),
('Cafeteria', 'Student Cafeteria', 'Student Center', 'Ground Floor', 'CAFE', 1),
('Gymnasium', 'Main Gymnasium', 'Sports Complex', 'Ground Floor', 'GYM', 1),
('Auditorium', 'Main Auditorium', 'Academic Building', 'Ground Floor', 'AUD', 1),
('Classroom A101', 'Academic Building Room A101', 'Academic Building', '1st Floor', 'A101', 1),
('Classroom A201', 'Academic Building Room A201', 'Academic Building', '2nd Floor', 'A201', 1),
('Classroom A301', 'Academic Building Room A301', 'Academic Building', '3rd Floor', 'A301', 1),

-- North Campus locations
('NCL1 Ground Floor', 'North Computer Lab 1 - Ground Floor', 'North Computer Building', 'Ground Floor', 'NCL1', 2),
('NCL1 2nd Floor', 'North Computer Lab 1 - 2nd Floor', 'North Computer Building', '2nd Floor', 'NCL1-2F', 2),
('NCL2 Ground Floor', 'North Computer Lab 2 - Ground Floor', 'North Computer Building', 'Ground Floor', 'NCL2', 2),
('North Library', 'North Campus Library', 'North Library Building', 'Ground Floor', 'NLIB', 2),
('North Admin', 'North Campus Administration', 'North Admin Building', '1st Floor', 'NADMIN', 2),
('North Cafeteria', 'North Campus Cafeteria', 'North Student Center', 'Ground Floor', 'NCAFE', 2),
('North Gym', 'North Campus Gymnasium', 'North Sports Complex', 'Ground Floor', 'NGYM', 2),
('Classroom N101', 'North Academic Building Room N101', 'North Academic Building', '1st Floor', 'N101', 2),
('Classroom N201', 'North Academic Building Room N201', 'North Academic Building', '2nd Floor', 'N201', 2),

-- Congressional Campus locations
('CCL1 Ground Floor', 'Congressional Computer Lab 1', 'Congressional IT Building', 'Ground Floor', 'CCL1', 3),
('CCL2 Ground Floor', 'Congressional Computer Lab 2', 'Congressional IT Building', 'Ground Floor', 'CCL2', 3),
('Congressional Library', 'Congressional Campus Library', 'Congressional Library Building', 'Ground Floor', 'CLIB', 3),
('Congressional Admin', 'Congressional Administration', 'Congressional Admin Building', '1st Floor', 'CADMIN', 3),
('Congressional Cafeteria', 'Congressional Campus Cafeteria', 'Congressional Student Center', 'Ground Floor', 'CCAFE', 3),
('Classroom C101', 'Congressional Academic Room C101', 'Congressional Academic Building', '1st Floor', 'C101', 3),
('Classroom C201', 'Congressional Academic Room C201', 'Congressional Academic Building', '2nd Floor', 'C201', 3),

-- Common areas (no specific campus)
('Parking Lot A', 'Main Parking Area A', 'Outdoor', 'Ground Level', 'PARK-A', NULL),
('Parking Lot B', 'Main Parking Area B', 'Outdoor', 'Ground Level', 'PARK-B', NULL),
('Campus Grounds', 'General Campus Area', 'Outdoor', 'Ground Level', 'GROUNDS', NULL),
('Student Dormitory', 'Student Housing Area', 'Dormitory Building', 'Various', 'DORM', NULL);

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
CREATE INDEX idx_tickets_subcategory ON tickets(subcategory_id);
CREATE INDEX idx_tickets_location ON tickets(location_id);
CREATE INDEX idx_attachments_type ON ticket_attachments(attachment_type);

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
    u.campus_id as requester_campus,
    c.name as campus_name,
    d.name as department_name,
    sc.name as category_name,
    ssc.name as subcategory_name,
    l.name as location_name,
    l.description as location_description,
    CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name,
    TIMESTAMPDIFF(HOUR, t.created_at, COALESCE(t.resolved_at, NOW())) as resolution_time_hours
FROM tickets t
LEFT JOIN users u ON t.requester_id = u.id
LEFT JOIN users staff ON t.assigned_to = staff.id
LEFT JOIN campuses c ON u.campus_id = c.id
LEFT JOIN departments d ON t.department_id = d.id
LEFT JOIN service_categories sc ON t.category_id = sc.id
LEFT JOIN service_subcategories ssc ON t.subcategory_id = ssc.id
LEFT JOIN locations l ON t.location_id = l.id;

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
        VALUES (NEW.id, OLD.status, NEW.status, COALESCE(NEW.assigned_to, 1), NOW());
    END IF;
END//

DELIMITER ;

-- Create stored procedures for common operations
DELIMITER //

CREATE PROCEDURE GetTicketStats(IN dept_id INT, IN date_from DATE, IN date_to DATE)
BEGIN
    SELECT 
        COUNT(*) as total_tickets,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_tickets,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tickets,
        SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_tickets,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
        SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) as on_hold_tickets,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_tickets,
        SUM(CASE WHEN status = 'reopen' THEN 1 ELSE 0 END) as reopen_tickets,
        AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) as avg_resolution_time,
        SUM(CASE WHEN priority = 'emergency' THEN 1 ELSE 0 END) as emergency_tickets,
        SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority_tickets
    FROM tickets 
    WHERE (dept_id IS NULL OR department_id = dept_id)
    AND DATE(created_at) BETWEEN date_from AND date_to;
END//

DELIMITER ;