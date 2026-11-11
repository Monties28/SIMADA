-- Migration fitur SIMADA untuk database existing
USE `simada`;

ALTER TABLE documents ADD COLUMN IF NOT EXISTS file_hash CHAR(64) NULL AFTER file_size;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS ocr_text MEDIUMTEXT NULL AFTER file_hash;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS ocr_status VARCHAR(30) NOT NULL DEFAULT 'not_processed' AFTER ocr_text;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS ocr_engine VARCHAR(80) NULL AFTER ocr_status;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS ocr_processed_at DATETIME NULL AFTER ocr_engine;

ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL AFTER jabatan;
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) NULL AFTER phone;
ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    target_role VARCHAR(20) NULL,
    title VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    link_url VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user_read (user_id, is_read),
    KEY idx_notifications_role_read (target_role, is_read),
    KEY idx_notifications_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_user_states (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    deleted_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_user (notification_id, user_id),
    KEY idx_notification_user_flags (user_id, is_read, is_deleted),
    KEY idx_notification_state_notification (notification_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    employee_name VARCHAR(150) NOT NULL,
    periode_bulan CHAR(7) NOT NULL,
    hadir INT NOT NULL DEFAULT 0,
    izin INT NOT NULL DEFAULT 0,
    sakit INT NOT NULL DEFAULT 0,
    alpha INT NOT NULL DEFAULT 0,
    terlambat INT NOT NULL DEFAULT 0,
    cluster_label VARCHAR(60) NULL,
    cluster_score DECIMAL(8,2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attendance_period (periode_bulan),
    KEY idx_attendance_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
