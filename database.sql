-- ─────────────────────────────────────────────────────────────────────────────
--  For Gloria — proposal app schema
--  Import this once:  mysql -u USER -p < database.sql
-- ─────────────────────────────────────────────────────────────────────────────

CREATE DATABASE IF NOT EXISTS `gloria_proposal`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `gloria_proposal`;

CREATE TABLE IF NOT EXISTS `responses` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `session_token`   CHAR(32)        NOT NULL,
    `comfortable`     ENUM('yes','no')         DEFAULT NULL,
    `likes`           TEXT            NULL,
    `dislikes`        TEXT            NULL,
    `future_serious`  TEXT            NULL,
    `lovely_future`   TEXT            NULL,
    `forever`         ENUM('yes','no','not_ready') DEFAULT NULL,
    `current_step`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `completed`       TINYINT(1)      NOT NULL DEFAULT 0,
    `ip_address`      VARCHAR(45)     NULL,
    `user_agent`      VARCHAR(255)    NULL,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_responses_session_token` (`session_token`),
    KEY `idx_responses_completed_created` (`completed`, `created_at`),
    KEY `idx_responses_created` (`created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
