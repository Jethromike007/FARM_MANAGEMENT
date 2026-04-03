-- ============================================================
-- FarmFlow MVP — Complete Database Schema
-- Version: 1.0.0
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================
-- Drop tables in reverse dependency order
-- ============================================================
DROP TABLE IF EXISTS `egg_production`;
DROP TABLE IF EXISTS `logs`;
DROP TABLE IF EXISTS `sales`;
DROP TABLE IF EXISTS `crops`;
DROP TABLE IF EXISTS `animals`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `farms`;

-- ============================================================
-- Table: farms
-- ============================================================
CREATE TABLE `farms` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150) NOT NULL,
  `state`      VARCHAR(100) NOT NULL,
  `city`       VARCHAR(100) NOT NULL,
  `type`       ENUM('crop','livestock','mixed','poultry','aquaculture','orchard') NOT NULL DEFAULT 'mixed',
  `size`       DECIMAL(10,2) NOT NULL COMMENT 'Size in hectares',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_farms_state` (`state`),
  INDEX `idx_farms_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registered farm entities';

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE `users` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`                  VARCHAR(150) NOT NULL,
  `email`                 VARCHAR(255) NOT NULL,
  `password`              VARCHAR(255) NOT NULL COMMENT 'Bcrypt hashed',
  `role`                  ENUM('owner','manager','viewer') NOT NULL DEFAULT 'viewer',
  `farm_id`               INT UNSIGNED DEFAULT NULL COMMENT 'Primary farm assignment (NULL = all farms for owner)',
  `email_notifications`   TINYINT(1) NOT NULL DEFAULT 1,
  `theme_preference`      ENUM('light','dark') NOT NULL DEFAULT 'light',
  `last_login`            TIMESTAMP NULL DEFAULT NULL,
  `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_farm` (`farm_id`),
  CONSTRAINT `fk_users_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='System users with role-based access';

-- ============================================================
-- Table: animals
-- ============================================================
CREATE TABLE `animals` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `farm_id`       INT UNSIGNED NOT NULL,
  `type`          VARCHAR(100) NOT NULL COMMENT 'e.g. Cattle, Goat, Chicken, Pig',
  `breed`         VARCHAR(100) DEFAULT NULL,
  `quantity`      INT UNSIGNED NOT NULL DEFAULT 1,
  `birth_date`    DATE NOT NULL,
  `maturity_days` SMALLINT UNSIGNED NOT NULL COMMENT 'Days from birth to market-ready',
  `health_status` ENUM('healthy','sick','recovering','quarantined','deceased') NOT NULL DEFAULT 'healthy',
  `sold`          TINYINT(1) NOT NULL DEFAULT 0,
  `sold_date`     DATE DEFAULT NULL,
  `price_sold`    DECIMAL(12,2) DEFAULT NULL,
  `notes`         TEXT DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_animals_farm` (`farm_id`),
  INDEX `idx_animals_sold` (`sold`),
  INDEX `idx_animals_health` (`health_status`),
  CONSTRAINT `fk_animals_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Livestock and animal inventory';

-- ============================================================
-- Table: crops
-- ============================================================
CREATE TABLE `crops` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `farm_id`         INT UNSIGNED NOT NULL,
  `type`            VARCHAR(100) NOT NULL COMMENT 'e.g. Maize, Tomato, Cassava',
  `variety`         VARCHAR(100) DEFAULT NULL,
  `quantity`        DECIMAL(10,2) NOT NULL COMMENT 'In kg or units',
  `quantity_unit`   VARCHAR(20) NOT NULL DEFAULT 'kg',
  `planting_date`   DATE NOT NULL,
  `maturity_days`   SMALLINT UNSIGNED NOT NULL COMMENT 'Days from planting to harvest',
  `harvested`       TINYINT(1) NOT NULL DEFAULT 0,
  `harvested_date`  DATE DEFAULT NULL,
  `price_sold`      DECIMAL(12,2) DEFAULT NULL,
  `notes`           TEXT DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_crops_farm` (`farm_id`),
  INDEX `idx_crops_harvested` (`harvested`),
  INDEX `idx_crops_planting` (`planting_date`),
  CONSTRAINT `fk_crops_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Crop planting and harvest tracking';

-- ============================================================
-- Table: egg_production
-- ============================================================
CREATE TABLE `egg_production` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `farm_id`       INT UNSIGNED NOT NULL,
  `animal_id`     INT UNSIGNED DEFAULT NULL COMMENT 'Poultry flock reference',
  `date_produced` DATE NOT NULL,
  `quantity`      INT UNSIGNED NOT NULL COMMENT 'Number of eggs',
  `daily_target`  INT UNSIGNED DEFAULT NULL COMMENT 'Expected eggs for comparison',
  `sold`          TINYINT(1) NOT NULL DEFAULT 0,
  `price_sold`    DECIMAL(10,2) DEFAULT NULL COMMENT 'Revenue if eggs were sold',
  `notes`         TEXT DEFAULT NULL,
  `recorded_by`   INT UNSIGNED DEFAULT NULL COMMENT 'User who recorded this entry',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_egg_farm` (`farm_id`),
  INDEX `idx_egg_date` (`date_produced`),
  INDEX `idx_egg_animal` (`animal_id`),
  CONSTRAINT `fk_egg_farm`   FOREIGN KEY (`farm_id`)   REFERENCES `farms`   (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_egg_animal` FOREIGN KEY (`animal_id`) REFERENCES `animals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_egg_user`   FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Daily egg production records';

-- ============================================================
-- Table: sales  (unified accounting ledger)
-- ============================================================
CREATE TABLE `sales` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `farm_id`      INT UNSIGNED NOT NULL,
  `entity_type`  ENUM('animal','crop','egg') NOT NULL,
  `entity_id`    INT UNSIGNED NOT NULL COMMENT 'ID from animals / crops / egg_production',
  `sale_date`    DATE NOT NULL,
  `quantity`     DECIMAL(10,2) NOT NULL,
  `unit_price`   DECIMAL(12,2) NOT NULL,
  `total_amount` DECIMAL(14,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `buyer_name`   VARCHAR(150) DEFAULT NULL,
  `notes`        TEXT DEFAULT NULL,
  `recorded_by`  INT UNSIGNED DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_sales_farm` (`farm_id`),
  INDEX `idx_sales_entity` (`entity_type`, `entity_id`),
  INDEX `idx_sales_date` (`sale_date`),
  CONSTRAINT `fk_sales_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sales_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Unified revenue ledger for all sold items';

-- ============================================================
-- Table: logs  (audit trail)
-- ============================================================
CREATE TABLE `logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `action_type` ENUM('create','update','delete','login','logout','sell','harvest','record_eggs','email_sent') NOT NULL,
  `entity_type` VARCHAR(50) DEFAULT NULL COMMENT 'farms|animals|crops|egg_production|sales|users',
  `entity_id`   INT UNSIGNED DEFAULT NULL,
  `description` TEXT NOT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `timestamp`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_logs_user` (`user_id`),
  INDEX `idx_logs_action` (`action_type`),
  INDEX `idx_logs_entity` (`entity_type`, `entity_id`),
  INDEX `idx_logs_time` (`timestamp`),
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Full audit trail for all system actions';

COMMIT;
