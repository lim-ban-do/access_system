-- Smart Attendance System (SAS) Database Schema
-- Created on: 2026-06-19
-- Compatible with MySQL 5.7+ and MySQL 8.0+

CREATE DATABASE IF NOT EXISTS `smart_attendance_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `smart_attendance_db`;

-- 1. Table: users (System administrators and Teachers)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fullname` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('Admin', 'Teacher') NOT NULL DEFAULT 'Teacher',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Table: classes (Academic classes e.g., Grade 10-A)
CREATE TABLE IF NOT EXISTS `classes` (
    `class_id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_name` VARCHAR(50) NOT NULL UNIQUE,
    `academic_year` VARCHAR(20) NOT NULL
) ENGINE=InnoDB;

-- 3. Table: students (Enrolled students linked to a specific class)
CREATE TABLE IF NOT EXISTS `students` (
    `student_id` INT AUTO_INCREMENT PRIMARY KEY,
    `fullname` VARCHAR(100) NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `class_id` INT NOT NULL,
    `qr_code` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Table: attendance (Log of student attendance status per date)
CREATE TABLE IF NOT EXISTS `attendance` (
    `attendance_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `class_id` INT NOT NULL,
    `attendance_date` DATE NOT NULL,
    `time_in` TIME DEFAULT NULL,
    `status` ENUM('Present', 'Absent', 'Late') NOT NULL,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_attendance_day` (`student_id`, `class_id`, `attendance_date`)
) ENGINE=InnoDB;

-- Insert default admin and teacher accounts
-- Admin: username = admin, password = adminpassword
-- Teacher: username = teacher, password = teacherpassword
INSERT INTO `users` (`fullname`, `username`, `password`, `role`) VALUES
('System Administrator', 'admin', '$2y$10$RJHbHqS6ZAAQEOR6aMyxyu1avCw4L.Mw2AACJDA7COjX0t16/8vDW', 'Admin'),
('Class Teacher', 'teacher', '$2y$10$2cv2GE0MWQwyRUZsgk2bTOJ1wDouoEBx/CPEgvO1j4DlV1jJT6I/m', 'Teacher')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- Insert some sample classes
INSERT INTO `classes` (`class_name`, `academic_year`) VALUES
('Class 10-A', '2026-2027'),
('Class 11-B', '2026-2027'),
('Class 12-C', '2026-2027')
ON DUPLICATE KEY UPDATE `class_name` = VALUES(`class_name`);
