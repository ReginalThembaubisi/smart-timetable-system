-- Create work_experience table for resume/CV builder
CREATE TABLE IF NOT EXISTS work_experience (
    experience_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    job_title VARCHAR(255) NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    start_month INT,
    start_year INT,
    end_month INT,
    end_year INT,
    is_current_job BOOLEAN DEFAULT FALSE,
    description TEXT,
    bullet_points JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create pre_written_examples table for job title examples
CREATE TABLE IF NOT EXISTS pre_written_examples (
    example_id INT AUTO_INCREMENT PRIMARY KEY,
    job_title VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    bullet_points JSON NOT NULL,
    keywords TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job_title (job_title),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample pre-written examples
INSERT INTO pre_written_examples (job_title, category, bullet_points, keywords) VALUES
('Team Lead - Library Management System Project', 'Project', 
'["Collaborated with a group of five students to design and develop a comprehensive library management system.", "Delegated tasks, assisted teammates with coding challenges, and ensured project milestones were achieved on time.", "Demonstrated problem-solving, leadership, and effective communication throughout the project."]',
'team lead, library, management, system, project, collaboration, leadership'),

('Diploma in ICT in Application Development', 'Education',
'["Self-motivated, with a strong sense of personal responsibility.", "Excellent communication skills, both verbal and written.", "Proven ability to learn quickly and adapt to new situations.", "Skilled at working independently and collaboratively in a team environment."]',
'diploma, ICT, application development, communication, teamwork'),

('Cashier', 'Retail',
'["Processed customer transactions accurately and efficiently.", "Maintained a clean and organized checkout area.", "Handled cash, credit, and debit card payments.", "Provided excellent customer service and resolved customer inquiries."]',
'cashier, retail, customer service, transactions, payments'),

('Customer Service Representative', 'Customer Service',
'["Responded to customer inquiries via phone, email, and in-person.", "Resolved customer complaints and issues in a timely manner.", "Maintained detailed records of customer interactions.", "Collaborated with team members to improve customer satisfaction."]',
'customer service, communication, problem-solving, teamwork'),

('Software Developer Intern', 'Technology',
'["Assisted in developing and maintaining web applications using modern frameworks.", "Participated in code reviews and team meetings.", "Debugged and fixed software defects.", "Documented code and technical processes."]',
'software developer, programming, web development, debugging'),

('Data Entry Clerk', 'Administrative',
'["Entered data accurately into database systems.", "Verified and corrected data discrepancies.", "Maintained confidentiality of sensitive information.", "Met daily data entry quotas and deadlines."]',
'data entry, administrative, accuracy, attention to detail'),

('IT Support Technician', 'Technology',
'["Provided technical support to end users for hardware and software issues.", "Installed and configured computer systems and applications.", "Troubleshot network connectivity problems.", "Maintained IT equipment inventory and documentation."]',
'IT support, troubleshooting, technical, hardware, software'),

('Web Developer', 'Technology',
'["Developed responsive web applications using HTML, CSS, and JavaScript.", "Collaborated with designers to implement user interfaces.", "Optimized website performance and user experience.", "Tested and debugged web applications across multiple browsers."]',
'web developer, HTML, CSS, JavaScript, responsive design'),

('Database Administrator', 'Technology',
'["Designed and maintained database systems.", "Optimized database performance and queries.", "Implemented backup and recovery procedures.", "Ensured data security and integrity."]',
'database, SQL, administration, security, backup'),

('Project Manager', 'Management',
'["Planned and executed projects from initiation to completion.", "Coordinated team members and resources.", "Tracked project progress and milestones.", "Communicated with stakeholders and prepared project reports."]',
'project management, coordination, leadership, planning');

