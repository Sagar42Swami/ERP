-- Centralized Corporate ERP System Database Schema
-- Database: login_system

CREATE DATABASE IF NOT EXISTS login_system;
USE login_system;

SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables in order of dependency
DROP TABLE IF EXISTS employee_projects;
DROP TABLE IF EXISTS payroll;
DROP TABLE IF EXISTS leaves;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS departments;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. DEPARTMENTS Table
CREATE TABLE departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) UNIQUE NOT NULL,
    description VARCHAR(255) NULL,
    location VARCHAR(100) NULL,
    manager_id INT NULL, -- FK to employees.employee_id (set to NULL if manager is deleted)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. EMPLOYEES Table
CREATE TABLE employees (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15) UNIQUE NOT NULL,
    gender ENUM('MALE', 'FEMALE', 'OTHER') NULL,
    address VARCHAR(255) NULL,
    hire_date DATE NOT NULL,
    designation VARCHAR(100) NOT NULL,
    salary DECIMAL(10,2) NOT NULL,
    department_id INT NOT NULL, -- FK to departments
    manager_id INT NULL, -- FK to employees (self-referencing reporting manager)
    employment_status ENUM('ACTIVE', 'INACTIVE', 'RESIGNED') DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_salary CHECK (salary >= 0),
    FOREIGN KEY (department_id) REFERENCES departments(department_id),
    FOREIGN KEY (manager_id) REFERENCES employees(employee_id) ON DELETE SET NULL
);

-- Complete circular dependency for departments manager
ALTER TABLE departments ADD CONSTRAINT fk_dept_manager FOREIGN KEY (manager_id) REFERENCES employees(employee_id) ON DELETE SET NULL;

-- 3. USERS Table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('ADMIN', 'HR', 'MANAGER', 'EMPLOYEE') NOT NULL,
    employee_id INT NULL, -- FK to employees (NULL for system admin-only accounts)
    account_status ENUM('ACTIVE', 'INACTIVE') DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE SET NULL
);

-- 4. ATTENDANCE Table
CREATE TABLE attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL, -- FK to employees
    attendance_date DATE NOT NULL,
    check_in TIME NULL,
    check_out TIME NULL,
    status ENUM('PRESENT', 'ABSENT', 'HALF_DAY', 'LEAVE') NOT NULL,
    working_hours DECIMAL(5,2) NULL,
    remarks VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_employee_date (employee_id, attendance_date),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

-- 5. LEAVES Table
CREATE TABLE leaves (
    leave_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL, -- FK to employees
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    leave_type ENUM('CASUAL', 'SICK', 'EARNED', 'UNPAID') NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
    approved_by INT NULL, -- FK to employees (Manager or HR)
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES employees(employee_id) ON DELETE SET NULL
);

-- 6. PAYROLL Table
CREATE TABLE payroll (
    payroll_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL, -- FK to employees
    salary_month DATE NOT NULL, -- Unique combo to restrict duplicate monthly payrolls
    basic_salary DECIMAL(10,2) NOT NULL,
    allowance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    bonus DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    deduction DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    net_salary DECIMAL(10,2) NOT NULL,
    payment_status ENUM('PENDING', 'PAID') DEFAULT 'PENDING',
    payment_date DATE NULL DEFAULT NULL,
    UNIQUE KEY uq_employee_month (employee_id, salary_month),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

-- 7. PROJECTS Table
CREATE TABLE projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL DEFAULT NULL,
    budget DECIMAL(12,2) NOT NULL,
    status ENUM('PLANNED', 'ACTIVE', 'COMPLETED', 'CANCELLED') DEFAULT 'PLANNED',
    manager_id INT NULL, -- FK to employees (Manager responsible for the project)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_budget CHECK (budget >= 0),
    FOREIGN KEY (manager_id) REFERENCES employees(employee_id) ON DELETE SET NULL
);

-- 8. EMPLOYEE_PROJECTS Table (Resolves M:N Relationship)
CREATE TABLE employee_projects (
    employee_id INT NOT NULL,
    project_id INT NOT NULL,
    role VARCHAR(100) NOT NULL,
    assigned_date DATE NOT NULL,
    assignment_status ENUM('ACTIVE', 'COMPLETED') DEFAULT 'ACTIVE',
    PRIMARY KEY (employee_id, project_id),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- SEED DATA
-- --------------------------------------------------------

-- Insert Initial Departments
INSERT INTO departments (department_id, department_name, description, location, manager_id) VALUES
(1, 'Management', 'Executive leadership and core administration.', 'Executive Suite - Floor 5', NULL),
(2, 'Human Resources', 'Talent acquisition, payroll, employee welfare.', 'Admin Wing - Floor 2', NULL),
(3, 'Engineering', 'Software engineering and IT operations.', 'Tech Lab - Floor 3', NULL),
(4, 'Sales', 'Business development and client acquisition.', 'Sales Room - Floor 1', NULL);

-- Insert Initial Employees
-- Note: manager_id values will be updated after inserting the records to prevent FK issues.
INSERT INTO employees (employee_id, first_name, last_name, email, phone, gender, address, hire_date, designation, salary, department_id, manager_id, employment_status) VALUES
(1, 'Admin', 'User', 'admin@erp.com', '555-0100', 'OTHER', '123 ERP Main Office', '2025-01-01', 'System Administrator', 95000.00, 1, NULL, 'ACTIVE'),
(2, 'Sarah', 'Jenkins', 'hr@erp.com', '555-0102', 'FEMALE', '456 HR Blvd', '2025-02-15', 'HR Manager', 65000.00, 2, 1, 'ACTIVE'),
(3, 'David', 'Miller', 'manager@erp.com', '555-0103', 'MALE', '789 Manager Way', '2025-03-01', 'Engineering Manager', 85000.00, 3, 1, 'ACTIVE'),
(4, 'Alex', 'Smith', 'employee@erp.com', '555-0104', 'MALE', '101 Developer Lane', '2025-04-01', 'Software Engineer', 50000.00, 3, 3, 'ACTIVE');

-- Update Departments with Manager IDs
UPDATE departments SET manager_id = 1 WHERE department_id = 1;
UPDATE departments SET manager_id = 2 WHERE department_id = 2;
UPDATE departments SET manager_id = 3 WHERE department_id = 3;

-- Insert User Credentials (Hashed password for 'password123' is $2y$10$gE1er5pwmod4LgcKk0lpUO9IFU68i0VsNqP9NzEC3Bltx5K5MmBLy)
INSERT INTO users (user_id, username, email, password_hash, role, employee_id, account_status) VALUES
(1, 'admin', 'admin@erp.com', '$2y$10$gE1er5pwmod4LgcKk0lpUO9IFU68i0VsNqP9NzEC3Bltx5K5MmBLy', 'ADMIN', 1, 'ACTIVE'),
(2, 'hr_sarah', 'hr@erp.com', '$2y$10$gE1er5pwmod4LgcKk0lpUO9IFU68i0VsNqP9NzEC3Bltx5K5MmBLy', 'HR', 2, 'ACTIVE'),
(3, 'manager_david', 'manager@erp.com', '$2y$10$gE1er5pwmod4LgcKk0lpUO9IFU68i0VsNqP9NzEC3Bltx5K5MmBLy', 'MANAGER', 3, 'ACTIVE'),
(4, 'alex_dev', 'employee@erp.com', '$2y$10$gE1er5pwmod4LgcKk0lpUO9IFU68i0VsNqP9NzEC3Bltx5K5MmBLy', 'EMPLOYEE', 4, 'ACTIVE');

-- Seed Attendance Records
INSERT INTO attendance (employee_id, attendance_date, check_in, check_out, status, working_hours, remarks) VALUES
(4, '2026-08-22', '09:00:00', '17:30:00', 'PRESENT', 8.50, 'Regular check-in'),
(4, '2026-08-23', '08:55:00', '17:00:00', 'PRESENT', 8.08, 'Completed daily tasks');

-- Seed Leave Applications
INSERT INTO leaves (employee_id, start_date, end_date, leave_type, reason, status, approved_by, approved_at) VALUES
(4, '2026-08-28', '2026-08-29', 'CASUAL', 'Family function attendance', 'PENDING', NULL, NULL),
(4, '2026-08-10', '2026-08-10', 'SICK', 'High fever and cold', 'APPROVED', 2, '2026-08-10 10:15:00');

-- Seed Payroll History
INSERT INTO payroll (employee_id, salary_month, basic_salary, allowance, bonus, deduction, tax, net_salary, payment_status, payment_date) VALUES
(4, '2026-07-01', 50000.00, 5000.00, 2000.00, 1000.00, 3000.00, 53000.00, 'PAID', '2026-07-31'),
(4, '2026-08-01', 50000.00, 5000.00, 0.00, 0.00, 3000.00, 52000.00, 'PENDING', NULL);

-- Seed Projects
INSERT INTO projects (project_id, project_name, description, start_date, end_date, budget, status, manager_id) VALUES
(1, 'Centralized ERP Portal', 'Development of the unified corporate dashboard for all departments.', '2026-04-15', '2026-10-31', 150000.00, 'ACTIVE', 3),
(2, 'Analytics Engine', 'Big data analytics dashboard and warehouse migration.', '2026-09-01', NULL, 80000.00, 'PLANNED', 3);

-- Seed Employee Project Assignments
INSERT INTO employee_projects (employee_id, project_id, role, assigned_date, assignment_status) VALUES
(4, 1, 'Full-Stack Developer', '2026-04-20', 'ACTIVE');
