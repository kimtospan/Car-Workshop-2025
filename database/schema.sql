-- Car Workshop Database Schema
-- Based on Greek requirements document

-- Drop existing tables (in correct order due to foreign keys)
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS mechanics;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS user_activity_logs;
DROP TABLE IF EXISTS failed_login_attempts;
DROP TABLE IF EXISTS user_sessions;
DROP TABLE IF EXISTS users;

-- 1. USERS TABLE (Χρήστης)
-- Base user table with authentication credentials and role assignment
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    id_number VARCHAR(20) NOT NULL UNIQUE,
    role ENUM('visitor', 'customer', 'mechanic', 'secretary') NOT NULL DEFAULT 'visitor',
    status ENUM('inactive', 'active', 'suspended') NOT NULL DEFAULT 'inactive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_id_number (id_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. CUSTOMERS TABLE (Πελάτης)
-- Extended information for customers - ΑΦΜ + address required
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    afm VARCHAR(20) NOT NULL UNIQUE,
    address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_afm (afm),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. MECHANICS TABLE (Μηχανικός)
-- Extended information for mechanics - specialty required
CREATE TABLE mechanics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    specialty VARCHAR(200) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_specialty (specialty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. VEHICLES TABLE (Αυτοκίνητο)
-- Customer vehicles with detailed specifications - all fields required
CREATE TABLE vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    serial_number VARCHAR(50) NOT NULL UNIQUE,
    model VARCHAR(100) NOT NULL,
    brand VARCHAR(100) NOT NULL,
    vehicle_type ENUM('επιβατικό', 'φορτηγό', 'λεωφορείο') NOT NULL,
    engine_type ENUM('ηλεκτροκίνητο', 'πετρελαιοκίνητο', 'υγραέριο', 'μεικτό') NOT NULL,
    door_count INT NOT NULL,
    wheel_count INT NOT NULL,
    production_date DATE NOT NULL,
    acquisition_year YEAR NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_customer_id (customer_id),
    INDEX idx_serial_number (serial_number),
    INDEX idx_brand_model (brand, model),
    INDEX idx_vehicle_type (vehicle_type),
    INDEX idx_engine_type (engine_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. APPOINTMENTS TABLE (Ραντεβού)
-- Customer appointments with state management
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    vehicle_id INT NULL,
    mechanic_id INT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    reason ENUM('επιδιόρθωση', 'σέρβις') NOT NULL,
    problem_description TEXT NULL,
    status ENUM('ΔΗΜΙΟΥΡΓΗΜΕΝΟ', 'ΣΕ ΕΞΕΛΙΞΗ', 'ΠΕΡΑΙΩΜΕΝΟ', 'ΑΚΥΡΩΜΕΝΟ') NOT NULL DEFAULT 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ',
    total_cost DECIMAL(10,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (mechanic_id) REFERENCES mechanics(id) ON DELETE SET NULL,
    INDEX idx_customer_id (customer_id),
    INDEX idx_vehicle_id (vehicle_id),
    INDEX idx_mechanic_id (mechanic_id),
    INDEX idx_appointment_date (appointment_date),
    INDEX idx_status (status),
    INDEX idx_reason (reason),
    
    -- Constraint: problem_description required when reason is 'επιδιόρθωση'
    CONSTRAINT chk_problem_description 
        CHECK (reason = 'σέρβις' OR (reason = 'επιδιόρθωση' AND problem_description IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TASKS TABLE (Εργασία)
-- Work tasks performed by mechanics on vehicles for appointments
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    mechanic_id INT NOT NULL,
    description TEXT NOT NULL,
    materials TEXT NOT NULL,
    completion_time INT NOT NULL,
    cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (mechanic_id) REFERENCES mechanics(id) ON DELETE CASCADE,
    INDEX idx_appointment_id (appointment_id),
    INDEX idx_mechanic_id (mechanic_id),
    INDEX idx_cost (cost)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

