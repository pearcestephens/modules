-- Photo Upload Session Tables
-- For QR code-based mobile photo uploads

-- Create sessions table
CREATE TABLE IF NOT EXISTS PHOTO_UPLOAD_SESSIONS (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    session_token VARCHAR(64) UNIQUE NOT NULL,
    transfer_id INT NOT NULL,
    transfer_type ENUM('stock_transfer', 'consignment', 'purchase_order') DEFAULT 'stock_transfer',
    user_id INT,
    outlet_id INT,
    expires_at DATETIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    photos_uploaded INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (session_token),
    INDEX idx_transfer (transfer_id, transfer_type),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Temporary photo upload sessions';

-- Create photos table
CREATE TABLE IF NOT EXISTS TRANSFER_PHOTOS (
    photo_id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    transfer_type ENUM('stock_transfer', 'consignment', 'purchase_order') DEFAULT 'stock_transfer',
    session_id INT,
    product_id INT NULL COMMENT 'Assigned product',
    issue_type ENUM('damaged', 'repaired', 'missing', 'other') NULL,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    notes TEXT NULL,
    uploaded_by_user_id INT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    assigned_at DATETIME NULL,
    INDEX idx_transfer (transfer_id, transfer_type),
    INDEX idx_session (session_id),
    INDEX idx_product (product_id),
    FOREIGN KEY (session_id) REFERENCES PHOTO_UPLOAD_SESSIONS(session_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Photos uploaded for transfers';

-- Auto-cleanup expired sessions (run daily)
CREATE EVENT IF NOT EXISTS cleanup_expired_photo_sessions
ON SCHEDULE EVERY 1 DAY
DO
    UPDATE PHOTO_UPLOAD_SESSIONS
    SET is_active = 0
    WHERE expires_at < NOW() AND is_active = 1;
