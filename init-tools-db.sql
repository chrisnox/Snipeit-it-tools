-- ============================================================
--  IT-Tools Database Schema
--  Created on first container start via docker-entrypoint-initdb.d
-- ============================================================

CREATE DATABASE IF NOT EXISTS snipeit_tools
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE snipeit_tools;

-- Configuration sections (JSON blobs per module)
CREATE TABLE IF NOT EXISTS settings (
    section     VARCHAR(64)  NOT NULL PRIMARY KEY,
    data        LONGTEXT,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Custom field mappings: logical name → SnipeIT custom_field key
CREATE TABLE IF NOT EXISTS custom_fields (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    logical_name  VARCHAR(64)  NOT NULL UNIQUE,
    label         VARCHAR(128),
    snipeit_key   VARCHAR(256),
    fallback_key  VARCHAR(256),
    tool          VARCHAR(32),
    sort_order    TINYINT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Signature audit trail
CREATE TABLE IF NOT EXISTS sign_signatures (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    user_name   VARCHAR(255) DEFAULT '',
    user_dept   VARCHAR(255) DEFAULT '',
    user_kst    VARCHAR(100) DEFAULT '',
    asset_ids   TEXT,
    asset_data  LONGTEXT,
    signed_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    uploaded    TINYINT(1)   DEFAULT 0,
    upload_log  TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AirWatch sync history
CREATE TABLE IF NOT EXISTS airwatch_sync_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    started_at  DATETIME,
    finished_at DATETIME,
    aw_devices  INT DEFAULT 0,
    created     INT DEFAULT 0,
    skipped     INT DEFAULT 0,
    errors      INT DEFAULT 0,
    log_text    LONGTEXT,
    status      VARCHAR(10) DEFAULT 'ok'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lansweeper CSV import history
CREATE TABLE IF NOT EXISTS lansweeper_sync_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    started_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    csv_files   INT DEFAULT 0,
    rows_read   INT DEFAULT 0,
    created     INT DEFAULT 0,
    skipped     INT DEFAULT 0,
    errors      INT DEFAULT 0,
    log_text    LONGTEXT,
    status      VARCHAR(10) DEFAULT 'ok'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
