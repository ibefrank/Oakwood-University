-- Oakwood University MySQL Database Schema
-- This file contains the MySQL database structure for the college website

-- Create database (if not exists)
CREATE DATABASE IF NOT EXISTS oakwood_university;
USE oakwood_university;

-- News table
CREATE TABLE IF NOT EXISTS news (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    title VARCHAR(255) NOT NULL,
    excerpt TEXT NOT NULL,
    content TEXT,
    date DATE NOT NULL,
    category VARCHAR(50) NOT NULL,
    featured BOOLEAN DEFAULT false,
    author VARCHAR(100),
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Contact submissions table
CREATE TABLE IF NOT EXISTS contact_submissions (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    newsletter_subscription BOOLEAN DEFAULT false,
    ip_address VARCHAR(45),
    status VARCHAR(20) DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Newsletter subscribers table
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    email VARCHAR(150) UNIQUE NOT NULL,
    name VARCHAR(100),
    subscription_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    active BOOLEAN DEFAULT true,
    ip_address VARCHAR(45),
    unsubscribe_token VARCHAR(64) UNIQUE
);

-- Academic programs table
CREATE TABLE IF NOT EXISTS academic_programs (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    name VARCHAR(150) NOT NULL,
    degree_type VARCHAR(50) NOT NULL,
    department VARCHAR(100) NOT NULL,
    description TEXT,
    duration_years INTEGER,
    credits_required INTEGER,
    tuition_per_year DECIMAL(10,2),
    program_type ENUM('undergraduate', 'graduate', 'online'),
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Faculty table
CREATE TABLE IF NOT EXISTS faculty (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    title VARCHAR(100),
    department VARCHAR(100),
    email VARCHAR(150),
    phone VARCHAR(20),
    office_location VARCHAR(100),
    bio TEXT,
    specializations JSON,
    hire_date DATE,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Students table (basic info for demonstrations)
CREATE TABLE IF NOT EXISTS students (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    student_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE,
    enrollment_date DATE,
    graduation_date DATE,
    program_id VARCHAR(36),
    status VARCHAR(20) DEFAULT 'active',
    gpa DECIMAL(3,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES academic_programs(id)
);

-- Admissions applications table
CREATE TABLE IF NOT EXISTS admissions_applications (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    application_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE,
    program_id VARCHAR(36),
    application_type ENUM('undergraduate', 'graduate', 'transfer'),
    gpa DECIMAL(3,2),
    test_scores JSON,
    status VARCHAR(20) DEFAULT 'submitted',
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    decision_date TIMESTAMP NULL,
    documents JSON,
    FOREIGN KEY (program_id) REFERENCES academic_programs(id)
);

-- Events table
CREATE TABLE IF NOT EXISTS events (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    title VARCHAR(200) NOT NULL,
    description TEXT,
    event_date TIMESTAMP NOT NULL,
    end_date TIMESTAMP,
    location VARCHAR(200),
    category VARCHAR(50),
    capacity INTEGER,
    registration_required BOOLEAN DEFAULT false,
    featured BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for better performance
CREATE INDEX idx_news_date ON news(date DESC);
CREATE INDEX idx_news_category ON news(category);
CREATE INDEX idx_news_featured ON news(featured);
CREATE INDEX idx_contact_submissions_date ON contact_submissions(created_at DESC);
CREATE INDEX idx_newsletter_email ON newsletter_subscribers(email);
CREATE INDEX idx_programs_department ON academic_programs(department);
CREATE INDEX idx_faculty_department ON faculty(department);
CREATE INDEX idx_students_program ON students(program_id);
CREATE INDEX idx_applications_status ON admissions_applications(status);
CREATE INDEX idx_events_date ON events(event_date);

-- Insert sample academic programs
INSERT INTO academic_programs (name, degree_type, department, description, duration_years, credits_required, program_type) VALUES
('Computer Science', 'Bachelor of Science', 'Engineering', 'Learn programming, algorithms, and software development to create innovative solutions for real-world problems.', 4, 120, 'undergraduate'),
('Electrical Engineering', 'Bachelor of Science', 'Engineering', 'Design and develop electrical systems, from microchips to power grids, shaping the future of technology.', 4, 128, 'undergraduate'),
('Software Engineering', 'Master of Science', 'Engineering', 'Advanced software development methodologies and project management for complex systems.', 2, 36, 'graduate'),
('Business Administration', 'Bachelor of Business Administration', 'Business', 'Comprehensive business education covering management, marketing, finance, and operations.', 4, 120, 'undergraduate'),
('Master of Business Administration', 'MBA', 'Business', 'Advanced business leadership program with specializations in various business areas.', 2, 48, 'graduate'),
('Psychology', 'Bachelor of Arts', 'Liberal Arts', 'Understand human behavior and mental processes through scientific research and practical application.', 4, 120, 'online'),
('Biology', 'Bachelor of Science', 'Sciences', 'Study living organisms and their interactions with the environment in our modern laboratories.', 4, 124, 'undergraduate'),
('Environmental Science', 'Master of Science', 'Sciences', 'Address environmental challenges through interdisciplinary scientific approaches.', 2, 36, 'graduate');

-- Sample faculty
INSERT INTO faculty (first_name, last_name, title, department, email, specializations, hire_date) VALUES
('Sarah', 'Mitchell', 'President', 'Administration', 'president@oakwood.edu', JSON_ARRAY('Higher Education Leadership', 'Strategic Planning'), '2020-01-15'),
('Michael', 'Chen', 'Vice President of Academic Affairs', 'Administration', 'vpacademic@oakwood.edu', JSON_ARRAY('Academic Administration', 'Curriculum Development'), '2019-08-01'),
('Emily', 'Rodriguez', 'Dean of Student Affairs', 'Student Services', 'deanstudents@oakwood.edu', JSON_ARRAY('Student Development', 'Campus Life'), '2018-09-01'),
('Dr. Robert', 'Johnson', 'Professor', 'Engineering', 'r.johnson@oakwood.edu', JSON_ARRAY('Computer Science', 'Machine Learning'), '2015-01-15'),
('Dr. Maria', 'Garcia', 'Associate Professor', 'Business', 'm.garcia@oakwood.edu', JSON_ARRAY('Marketing', 'Digital Strategy'), '2017-08-20'),
('Dr. James', 'Wilson', 'Professor', 'Sciences', 'j.wilson@oakwood.edu', JSON_ARRAY('Biology', 'Genetics'), '2012-09-01'),
('Dr. Lisa', 'Brown', 'Assistant Professor', 'Liberal Arts', 'l.brown@oakwood.edu', JSON_ARRAY('Psychology', 'Cognitive Science'), '2020-01-10');

-- Sample news from existing data
INSERT INTO news (title, excerpt, date, category, featured, author) VALUES
('Oakwood University Announces New Engineering Research Center', 'The university has unveiled plans for a state-of-the-art engineering research facility that will focus on sustainable technology and renewable energy solutions.', '2025-01-15', 'academic', true, 'Dr. Robert Johnson'),
('Record-Breaking Enrollment for Fall 2025 Semester', 'Oakwood University welcomes its largest incoming class ever, with over 3,500 new students joining the campus community this fall.', '2025-01-10', 'announcement', true, 'Emily Rodriguez'),
('Professor Dr. Sarah Chen Receives National Science Foundation Grant', 'Dr. Chen from the Biology Department has been awarded a $2.5 million grant to study climate change impacts on marine ecosystems.', '2025-01-08', 'research', true, 'Dr. James Wilson'),
('Annual Spring Career Fair Attracts 200+ Employers', 'Students had the opportunity to network with industry professionals from Fortune 500 companies and emerging startups at this year''s career fair.', '2025-01-05', 'student', false, 'Career Services'),
('New Business Leadership Program Launches This Fall', 'The School of Business introduces an innovative leadership development program designed to prepare students for executive roles in the modern business world.', '2025-01-03', 'academic', false, 'Dr. Maria Garcia'),
('Oakwood University Ranked Among Top 50 Universities Nationwide', 'The latest U.S. News & World Report rankings place Oakwood University at #47 among national universities, recognizing excellence in education and research.', '2025-01-01', 'announcement', false, 'Administration'),
('Student Research Team Wins International Competition', 'A team of computer science students from Oakwood University took first place in the Global Cybersecurity Challenge, beating teams from 50 countries.', '2024-12-28', 'student', false, 'Dr. Robert Johnson'),
('New Scholarship Program Supports First-Generation College Students', 'The university has established a comprehensive scholarship program providing full tuition support for qualified first-generation college students.', '2024-12-25', 'announcement', false, 'Financial Aid Office');

-- Sample events
INSERT INTO events (title, description, event_date, location, category, registration_required, featured) VALUES
('Fall 2025 Orientation Week', 'Welcome new students to campus with information sessions, campus tours, and social activities.', '2025-08-25 09:00:00', 'Student Center', 'orientation', true, true),
('Engineering Research Symposium', 'Annual showcase of student and faculty research projects in engineering and technology.', '2025-10-15 14:00:00', 'Engineering Building Auditorium', 'academic', false, true),
('Career Fair Spring 2025', 'Meet with employers from various industries and explore internship and job opportunities.', '2025-03-20 10:00:00', 'Athletic Center', 'career', true, false),
('Psychology Department Lecture Series', 'Monthly lecture series featuring renowned psychologists and researchers.', '2025-11-10 18:00:00', 'Liberal Arts Building', 'academic', false, false),
('Business Networking Night', 'Connect with local business leaders and alumni in an informal networking environment.', '2025-02-14 18:30:00', 'Business School Atrium', 'networking', true, false),
('Spring Concert by University Orchestra', 'Annual spring performance featuring classical and contemporary pieces.', '2025-04-12 19:00:00', 'Performing Arts Center', 'cultural', false, true);

-- Create views for common queries
CREATE VIEW featured_news AS
SELECT * FROM news WHERE featured = true ORDER BY date DESC;

CREATE VIEW active_programs AS
SELECT * FROM academic_programs WHERE active = true ORDER BY department, name;

CREATE VIEW recent_contact_submissions AS
SELECT * FROM contact_submissions ORDER BY created_at DESC LIMIT 50;
