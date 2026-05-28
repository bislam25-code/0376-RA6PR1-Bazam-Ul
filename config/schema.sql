-- ============================================
-- WorkTracker Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS worktracker
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE worktracker;

-- -------------------------------------------
-- Taula: usuaris
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS usuaris (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(100)    NOT NULL,
    cognom      VARCHAR(100)    NOT NULL,
    email       VARCHAR(255)    NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    rol         ENUM('admin', 'empleat') NOT NULL DEFAULT 'empleat',
    creat_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------
-- Taula: projectes
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS projectes (
    id              INT             AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(150)    NOT NULL,
    descripcio      TEXT            NULL,
    hores_estimades DECIMAL(8,2)    NOT NULL DEFAULT 0,
    creat_at        TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------
-- Taula: registres_hores
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS registres_hores (
    id              INT             AUTO_INCREMENT PRIMARY KEY,
    usuari_id       INT             NOT NULL,
    projecte_id     INT             NOT NULL,
    hora_entrada    DATETIME        NOT NULL,
    hora_sortida    DATETIME        NULL,
    hores_totals    DECIMAL(6,2)    NULL,
    data            DATE            NOT NULL,
    notes           TEXT            NULL,

    CONSTRAINT fk_registre_usuari
        FOREIGN KEY (usuari_id) REFERENCES usuaris(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_registre_projecte
        FOREIGN KEY (projecte_id) REFERENCES projectes(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;