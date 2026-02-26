-- ==========================================
-- 1. AUTHENTICATION SERVICE DATABASE
-- ==========================================
CREATE DATABASE IF NOT EXISTS auth_db;
USE auth_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- 2. PROFILE SERVICE DATABASE
-- ==========================================
CREATE DATABASE IF NOT EXISTS profile_db;
USE profile_db;

CREATE TABLE IF NOT EXISTS profiles (
    user_id INT PRIMARY KEY, -- This will match the ID from auth_db.users
    display_name VARCHAR(255) NOT NULL,
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Ensure the root user has access to everything from any host inside the Docker network
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%';
FLUSH PRIVILEGES;