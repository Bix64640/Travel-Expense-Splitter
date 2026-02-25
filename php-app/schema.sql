-- ============================================================
-- Travel Expense Splitter - Schema SQL
-- ============================================================
-- Exécuter ce script dans phpMyAdmin ou en ligne de commande :
--   mysql -u root -p < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS travel_splitter
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE travel_splitter;

-- ============================================================
-- 1. UTILISATEURS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)    NOT NULL,
    email       VARCHAR(255)    NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    role        ENUM('visitor','user','admin') NOT NULL DEFAULT 'user',
    is_active   TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. GROUPES DE VOYAGE
-- ============================================================
CREATE TABLE IF NOT EXISTS `groups` (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150)    NOT NULL,
    description TEXT            NULL,
    owner_id    INT UNSIGNED    NOT NULL,
    is_public   TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 3. MEMBRES D'UN GROUPE
-- ============================================================
CREATE TABLE IF NOT EXISTS group_members (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id     INT UNSIGNED    NOT NULL,
    user_id      INT UNSIGNED    NULL,
    display_name VARCHAR(100)    NOT NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 4. CATEGORIES DE DEPENSES
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Categories par defaut
INSERT INTO categories (name) VALUES
    ('Hebergement'),
    ('Transport'),
    ('Nourriture'),
    ('Activites'),
    ('Shopping'),
    ('Autre');

-- ============================================================
-- 5. DEPENSES
-- ============================================================
CREATE TABLE IF NOT EXISTS expenses (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id        INT UNSIGNED    NOT NULL,
    payer_member_id INT UNSIGNED    NOT NULL,
    category_id     INT UNSIGNED    NOT NULL,
    amount          DECIMAL(10,2)   NOT NULL,
    description     VARCHAR(255)    NULL,
    expense_date    DATE            NOT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id)        REFERENCES `groups`(id)        ON DELETE CASCADE,
    FOREIGN KEY (payer_member_id) REFERENCES group_members(id)   ON DELETE CASCADE,
    FOREIGN KEY (category_id)     REFERENCES categories(id)      ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 6. REPARTITION (SPLITS)
-- ============================================================
CREATE TABLE IF NOT EXISTS splits (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_id   INT UNSIGNED   NOT NULL,
    member_id    INT UNSIGNED   NOT NULL,
    share_amount DECIMAL(10,2)  NOT NULL,
    FOREIGN KEY (expense_id) REFERENCES expenses(id)       ON DELETE CASCADE,
    FOREIGN KEY (member_id)  REFERENCES group_members(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 7. REGLEMENTS (SETTLEMENTS)
-- ============================================================
CREATE TABLE IF NOT EXISTS settlements (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id        INT UNSIGNED   NOT NULL,
    from_member_id  INT UNSIGNED   NOT NULL,
    to_member_id    INT UNSIGNED   NOT NULL,
    amount          DECIMAL(10,2)  NOT NULL,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id)       REFERENCES `groups`(id)       ON DELETE CASCADE,
    FOREIGN KEY (from_member_id) REFERENCES group_members(id)  ON DELETE CASCADE,
    FOREIGN KEY (to_member_id)   REFERENCES group_members(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 8. NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED    NOT NULL,
    message    VARCHAR(500)    NOT NULL,
    is_read    TINYINT(1)      NOT NULL DEFAULT 0,
    link       VARCHAR(255)    NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- COMPTE ADMIN PAR DEFAUT
-- Mot de passe : admin123  (hash bcrypt)
-- ============================================================
INSERT INTO users (name, email, password_hash, role) VALUES
    ('Admin', 'admin@travel.local', '$2y$10$YJ9xVz0e8v5K5R5J5Q5Q5eFJHVwEz5Q5Q5Q5Q5Q5Q5Q5Q5Q5Q5Q', 'admin');
