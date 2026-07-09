-- ================================================================
-- GH Software Solution Database
-- ================================================================
-- Database : gh_software_db
-- Version  : 1.0
-- ================================================================

CREATE DATABASE IF NOT EXISTS `gh_software_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `gh_software_db`;

-- ----------------------------------------------------------------
-- Table: roles
-- ----------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id`          INT(11)      NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(50)  NOT NULL,
    `slug`        VARCHAR(50)  NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`name`, `slug`, `description`) VALUES
('Super Admin', 'super_admin', 'Full system access with all privileges'),
('Admin',       'admin',       'Manage users, sales, inventory and reports'),
('Employee',    'employee',    'View assigned tasks and manage own attendance');

-- ----------------------------------------------------------------
-- Table: job_roles
-- ----------------------------------------------------------------
DROP TABLE IF EXISTS `job_roles`;
CREATE TABLE `job_roles` (
    `id`          INT(11)      NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `slug`        VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_job_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `job_roles` (`name`, `slug`, `description`) VALUES
('Labor',          'labor',          'General construction labor worker'),
('Foreman',        'foreman',        'Site foreman / supervisor'),
('Mason',          'mason',          'Masonry and concrete work'),
('Electrician',    'electrician',    'Electrical installation and repair'),
('Plumber',        'plumber',        'Plumbing installation and repair'),
('Painter',        'painter',        'Interior and exterior painting'),
('Tile Setter',    'tile_setter',    'Floor and wall tile installation'),
('Roof Installer', 'roof_installer', 'Roofing installation and repair');

-- ----------------------------------------------------------------
-- Table: users
-- ----------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`            INT(11)                   NOT NULL AUTO_INCREMENT,
    `employee_id`   VARCHAR(20)               DEFAULT NULL COMMENT 'Auto-generated ID number (KMR#####)',
    `role_id`       INT(11)                   NOT NULL,
    `job_role_id`   INT(11)                   DEFAULT NULL,
    `first_name`    VARCHAR(100)              NOT NULL,
    `last_name`     VARCHAR(100)              NOT NULL,
    `username`      VARCHAR(100)              NOT NULL,
    `email`         VARCHAR(150)              NOT NULL,
    `password`      VARCHAR(255)              NOT NULL,
    `profile_image` VARCHAR(255)              DEFAULT NULL,
    `barcode`       VARCHAR(50)               DEFAULT NULL COMMENT 'ID badge code for scan station',
    `date_hired`    DATE                      DEFAULT NULL,
    `timesheet_type` ENUM('weekly','semi_monthly') NOT NULL DEFAULT 'weekly',
    `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `last_login`    TIMESTAMP                 NULL DEFAULT NULL,
    `created_at`    TIMESTAMP                 NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP                 NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_employee_id` (`employee_id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email`    (`email`),
    UNIQUE KEY `uq_users_barcode`  (`barcode`),
    KEY `fk_users_role_id`         (`role_id`),
    KEY `fk_users_job_role_id`     (`job_role_id`),
    CONSTRAINT `fk_users_role`
        FOREIGN KEY (`role_id`)     REFERENCES `roles`     (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_users_job_role`
        FOREIGN KEY (`job_role_id`) REFERENCES `job_roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Table: attendance
-- ----------------------------------------------------------------
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
    `id`             INT(11)      NOT NULL AUTO_INCREMENT,
    `user_id`        INT(11)      NOT NULL,
    `date`           DATE         NOT NULL,
    `time_in`        TIME         DEFAULT NULL,
    `time_out`       TIME         DEFAULT NULL,
    `time_in_photo`  VARCHAR(255) DEFAULT NULL,
    `time_out_photo` VARCHAR(255) DEFAULT NULL,
    `total_hours`    DECIMAL(5,2) DEFAULT NULL,
    `tardiness`      DECIMAL(6,2) DEFAULT NULL COMMENT 'minutes late',
    `overtime`       DECIMAL(5,2) DEFAULT NULL COMMENT 'overtime hours',
    `notes`          TEXT         DEFAULT NULL,
    `status`         ENUM('present','absent','half_day','holiday','leave') NOT NULL DEFAULT 'present',
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_attendance_user_date` (`user_id`, `date`),
    KEY `fk_att_user_id` (`user_id`),
    CONSTRAINT `fk_att_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Table: salary_requests
-- ----------------------------------------------------------------
DROP TABLE IF EXISTS `salary_requests`;
CREATE TABLE `salary_requests` (
    `id`          INT(11)                             NOT NULL AUTO_INCREMENT,
    `user_id`     INT(11)                             NOT NULL,
    `type`        ENUM('advance','borrow')            NOT NULL,
    `amount`      DECIMAL(10,2)                       NOT NULL,
    `week_start`  DATE                                NOT NULL COMMENT 'Monday of the request week',
    `status`      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `notes`       TEXT                                DEFAULT NULL,
    `admin_notes` TEXT                                DEFAULT NULL,
    `reviewed_by` INT(11)                             DEFAULT NULL,
    `reviewed_at` TIMESTAMP                           NULL DEFAULT NULL,
    `created_at`  TIMESTAMP                           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP                           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_salary_req_user_type_week` (`user_id`, `type`, `week_start`),
    KEY `fk_sr_user_id`        (`user_id`),
    KEY `fk_sr_reviewed_by`    (`reviewed_by`),
    CONSTRAINT `fk_sr_user`
        FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_sr_reviewer`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Table: week_confirmations
-- ----------------------------------------------------------------
DROP TABLE IF EXISTS `week_confirmations`;
CREATE TABLE `week_confirmations` (
    `id`           INT(11)   NOT NULL AUTO_INCREMENT,
    `user_id`      INT(11)   NOT NULL,
    `week_start`   DATE      NOT NULL COMMENT 'Monday of the confirmed week',
    `confirmed_by` INT(11)   NOT NULL,
    `confirmed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wc_user_week`   (`user_id`, `week_start`),
    KEY `fk_wc_user_id`            (`user_id`),
    KEY `fk_wc_confirmed_by`       (`confirmed_by`),
    CONSTRAINT `fk_wc_user`
        FOREIGN KEY (`user_id`)      REFERENCES `users` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_wc_confirmer`
        FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Table: notifications
-- ----------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id`         INT(11)      NOT NULL AUTO_INCREMENT,
    `user_id`    INT(11)      NOT NULL,
    `type`       VARCHAR(60)  NOT NULL,
    `title`      VARCHAR(255) NOT NULL,
    `message`    TEXT         DEFAULT NULL,
    `link`       VARCHAR(255) DEFAULT NULL,
    `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_notif_user_id` (`user_id`),
    KEY `idx_notif_read`   (`user_id`, `is_read`),
    CONSTRAINT `fk_notif_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Table: employee_payroll_info
-- ----------------------------------------------------------------
DROP TABLE IF EXISTS `employee_payroll_info`;
CREATE TABLE `employee_payroll_info` (
    `id`                  INT(11)       NOT NULL AUTO_INCREMENT,
    `user_id`             INT(11)       NOT NULL,
    `daily_rate`          DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Basic daily pay rate in PHP',
    `sss_enabled`         TINYINT(1)    NOT NULL DEFAULT 0,
    `sss_amount`          DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Fixed SSS employee share per payroll period',
    `philhealth_enabled`  TINYINT(1)    NOT NULL DEFAULT 0,
    `philhealth_amount`   DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Fixed PhilHealth employee share per payroll period',
    `pagibig_enabled`     TINYINT(1)    NOT NULL DEFAULT 0,
    `pagibig_amount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Fixed Pag-IBIG employee share per payroll period',
    `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_epi_user`  (`user_id`),
    KEY `fk_epi_user_id`      (`user_id`),
    CONSTRAINT `fk_epi_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Table: payroll_borrow_deductions
-- ----------------------------------------------------------------
DROP TABLE IF EXISTS `payroll_borrow_deductions`;
CREATE TABLE `payroll_borrow_deductions` (
    `id`            INT(11)       NOT NULL AUTO_INCREMENT,
    `user_id`       INT(11)       NOT NULL,
    `week_start`    DATE          NOT NULL COMMENT 'Monday of the payroll week',
    `deduct_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount to deduct from net pay for this period',
    `set_by`        INT(11)       NOT NULL,
    `set_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pbd_user_week`  (`user_id`, `week_start`),
    KEY `fk_pbd_user_id`           (`user_id`),
    KEY `fk_pbd_set_by`            (`set_by`),
    CONSTRAINT `fk_pbd_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_pbd_setter`
        FOREIGN KEY (`set_by`)  REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Table: payroll_line_items
-- ----------------------------------------------------------------
DROP TABLE IF EXISTS `payroll_line_items`;
CREATE TABLE `payroll_line_items` (
    `id`         INT(11)                      NOT NULL AUTO_INCREMENT,
    `user_id`    INT(11)                      NOT NULL,
    `week_start` DATE                         NOT NULL COMMENT 'Monday of the payroll week',
    `type`       ENUM('deduction','addition') NOT NULL,
    `label`      VARCHAR(150)                 NOT NULL COMMENT 'E.g. Remaining Borrow, Transport Allowance',
    `amount`     DECIMAL(10,2)                NOT NULL,
    `notes`      TEXT                         DEFAULT NULL,
    `added_by`   INT(11)                      NOT NULL,
    `added_at`   TIMESTAMP                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pli_user_week`  (`user_id`, `week_start`),
    KEY `fk_pli_added_by`   (`added_by`),
    CONSTRAINT `fk_pli_user`
        FOREIGN KEY (`user_id`)  REFERENCES `users` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_pli_adder`
        FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Table: payroll_computations  (for accounting — scaffolded, wired up by accounting module)
-- ----------------------------------------------------------------
DROP TABLE IF EXISTS `payroll_computations`;
CREATE TABLE `payroll_computations` (
    `id`                   INT(11)                              NOT NULL AUTO_INCREMENT,
    `user_id`              INT(11)                              NOT NULL,
    `week_start`           DATE                                 NOT NULL COMMENT 'Monday of the payroll week',
    `days_present`         TINYINT(3)                           NOT NULL DEFAULT 0,
    `daily_rate`           DECIMAL(10,2)                        NOT NULL DEFAULT 0.00,
    `gross_pay`            DECIMAL(10,2)                        NOT NULL DEFAULT 0.00,
    `overtime_pay`         DECIMAL(10,2)                        NOT NULL DEFAULT 0.00,
    `tardiness_deduction`  DECIMAL(10,2)                        NOT NULL DEFAULT 0.00,
    `sss_deduction`        DECIMAL(10,2)                        NOT NULL DEFAULT 0.00,
    `philhealth_deduction` DECIMAL(10,2)                        NOT NULL DEFAULT 0.00,
    `pagibig_deduction`    DECIMAL(10,2)                        NOT NULL DEFAULT 0.00,
    `advance_deduction`    DECIMAL(10,2)                        NOT NULL DEFAULT 0.00 COMMENT 'Approved advance requests',
    `borrow_deduction`     DECIMAL(10,2)                        NOT NULL DEFAULT 0.00 COMMENT 'Approved borrow requests',
    `total_deductions`     DECIMAL(10,2)                        NOT NULL DEFAULT 0.00,
    `net_pay`              DECIMAL(10,2)                        NOT NULL DEFAULT 0.00,
    `status`               ENUM('draft','approved','released')  NOT NULL DEFAULT 'draft',
    `notes`                TEXT                                 DEFAULT NULL,
    `processed_by`         INT(11)                              DEFAULT NULL,
    `processed_at`         TIMESTAMP                            NULL DEFAULT NULL,
    `created_at`           TIMESTAMP                            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP                            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pc_user_week`  (`user_id`, `week_start`),
    KEY `fk_pc_user_id`           (`user_id`),
    KEY `fk_pc_processed_by`      (`processed_by`),
    CONSTRAINT `fk_pc_user`
        FOREIGN KEY (`user_id`)      REFERENCES `users` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_pc_processor`
        FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Table: employee_emergency_contacts
-- ----------------------------------------------------------------
DROP TABLE IF EXISTS `employee_emergency_contacts`;
CREATE TABLE `employee_emergency_contacts` (
    `id`           INT(11)      NOT NULL AUTO_INCREMENT,
    `user_id`      INT(11)      NOT NULL,
    `name`         VARCHAR(150) NOT NULL,
    `relationship` VARCHAR(100) DEFAULT NULL,
    `phone`        VARCHAR(30)  NOT NULL,
    `phone_alt`    VARCHAR(30)  DEFAULT NULL,
    `address`      VARCHAR(255) DEFAULT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_eec_user` (`user_id`),
    KEY `fk_eec_user_id` (`user_id`),
    CONSTRAINT `fk_eec_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- SETUP INSTRUCTIONS
-- ================================================================
-- 1. Import this SQL file in phpMyAdmin or run: mysql -u root -p < database.sql
-- 2. Visit: http://localhost/gh_software_solution/setup
--    to create the default Super Admin account (password: Admin@123)
-- 3. DELETE application/controllers/Setup.php after setup is complete
-- ================================================================

-- ================================================================
-- MIGRATION: Add job_roles to existing database
-- Run these statements if you already have the database set up
-- ================================================================
-- CREATE TABLE `job_roles` ( ... ) -- see full definition above
-- ALTER TABLE `users` ADD COLUMN `job_role_id` INT(11) DEFAULT NULL AFTER `role_id`;
-- ALTER TABLE `users` ADD KEY `fk_users_job_role_id` (`job_role_id`);
-- ALTER TABLE `users` ADD CONSTRAINT `fk_users_job_role`
--     FOREIGN KEY (`job_role_id`) REFERENCES `job_roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
-- CREATE TABLE `employee_emergency_contacts` ( ... ) -- see full definition above
-- ================================================================

-- ================================================================
-- MIGRATION: Add date_hired to existing database
-- ================================================================
-- ALTER TABLE `users` ADD COLUMN `date_hired` DATE DEFAULT NULL AFTER `profile_image`;
-- ================================================================

-- ================================================================
-- MIGRATION: Remove Projects module from existing database
-- (System is now HRMS-only; admins can see all employees)
-- ================================================================
-- DROP TABLE IF EXISTS `user_projects`;
-- DROP TABLE IF EXISTS `projects`;
-- ================================================================

-- ================================================================
-- MIGRATION: Add barcode (ID badge code) for the attendance
-- scan station. Run on an existing database:
-- ================================================================
-- ALTER TABLE `users` ADD COLUMN `barcode` VARCHAR(50) DEFAULT NULL
--     COMMENT 'ID badge code for scan station' AFTER `profile_image`;
-- ALTER TABLE `users` ADD UNIQUE KEY `uq_users_barcode` (`barcode`);
-- ================================================================
