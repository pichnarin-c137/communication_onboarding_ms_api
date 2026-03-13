-- MySQL initialization script for Employee Management System

-- Set character set and collation
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Ensure database exists (already created by docker, but just in case)
CREATE DATABASE IF NOT EXISTS employee_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE employee_db;

-- Grant all privileges to the application user
GRANT ALL PRIVILEGES ON employee_db.* TO 'employee_user'@'%';
FLUSH PRIVILEGES;
