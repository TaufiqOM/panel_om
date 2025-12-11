-- Create login_history table
CREATE TABLE login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    uid INT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    success TINYINT(1) DEFAULT 0,
    INDEX idx_username (username),
    INDEX idx_login_time (login_time)
);
