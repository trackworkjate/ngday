-- ==============================================================================
-- Nigiwai Group: Monday-Style Project Management System Database Schema
-- Compatible with MySQL 8.x / MariaDB 10.x on Shared Hosting (cPanel / DirectAdmin / hostneverdie)
-- Character Set: utf8mb4 / utf8mb4_unicode_ci
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `item_updates`;
DROP TABLE IF EXISTS `items`;
DROP TABLE IF EXISTS `board_columns`;
DROP TABLE IF EXISTS `board_groups`;
DROP TABLE IF EXISTS `boards`;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------------------
-- 1. Boards Table
-- ------------------------------------------------------------------------------
CREATE TABLE `boards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. Board Groups Table (Sections / สาขา / แผนก)
-- ------------------------------------------------------------------------------
CREATE TABLE `board_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `board_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `color` VARCHAR(32) DEFAULT '#579BFC',
    `position` DOUBLE NOT NULL DEFAULT 1.0,
    `collapsed` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_groups_board` FOREIGN KEY (`board_id`) REFERENCES `boards`(`id`) ON DELETE CASCADE,
    INDEX `idx_board_pos` (`board_id`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. Dynamic Columns Metadata Table
-- ------------------------------------------------------------------------------
CREATE TABLE `board_columns` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `board_id` INT NOT NULL,
    `title` VARCHAR(100) NOT NULL,
    `type` ENUM('text', 'status', 'date', 'number', 'people', 'timeline', 'progress', 'dropdown') NOT NULL DEFAULT 'text',
    `is_subitem` TINYINT(1) NOT NULL DEFAULT 0,
    `settings` JSON NULL,
    `position` DOUBLE NOT NULL DEFAULT 1.0,
    CONSTRAINT `fk_columns_board` FOREIGN KEY (`board_id`) REFERENCES `boards`(`id`) ON DELETE CASCADE,
    INDEX `idx_board_col_pos` (`board_id`, `is_subitem`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. Tasks & Items Table (Hybrid Dynamic Storage for Main Items & Subitems)
-- ------------------------------------------------------------------------------
CREATE TABLE `items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `monday_item_id` VARCHAR(64) NULL,
    `board_id` INT NOT NULL,
    `group_id` INT NOT NULL,
    `parent_id` INT NULL,
    `name` VARCHAR(500) NOT NULL,
    `column_values` JSON NOT NULL,
    `position` DOUBLE NOT NULL DEFAULT 1.0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_items_board` FOREIGN KEY (`board_id`) REFERENCES `boards`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_items_group` FOREIGN KEY (`group_id`) REFERENCES `board_groups`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_items_parent` FOREIGN KEY (`parent_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
    INDEX `idx_group_pos` (`group_id`, `position`),
    INDEX `idx_parent_id` (`parent_id`),
    INDEX `idx_monday_id` (`monday_item_id`),
    INDEX `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. Item Activity & Updates Table (Comments / Work Logs from Monday)
-- ------------------------------------------------------------------------------
CREATE TABLE `item_updates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT NOT NULL,
    `monday_post_id` VARCHAR(64) NULL,
    `user_name` VARCHAR(150) NOT NULL,
    `content` LONGTEXT NOT NULL,
    `likes_count` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_updates_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
    INDEX `idx_item_updates` (`item_id`, `created_at`),
    INDEX `idx_monday_post` (`monday_post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
