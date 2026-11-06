-- PARKit — Schéma MySQL — Étape 1 : Réservation
-- Import : mysql -u root PARKit < schema.sql
-- (ou via phpMyAdmin, en sélectionnant la base "PARKit")

CREATE TABLE IF NOT EXISTS zones (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    code      VARCHAR(5) UNIQUE NOT NULL,
    label     VARCHAR(100) NOT NULL,
    capacity  INT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS forfaits (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    nom            VARCHAR(100) NOT NULL,
    type           ENUM('horaire','jour','abonnement') NOT NULL,
    duree_minutes  INT NULL,               -- NULL = variable (horaire) ou sur devis (abonnement)
    prix_tnd       DECIMAL(6,2) NULL,      -- NULL = sur devis ; pour "horaire" c'est le prix PAR HEURE
    grace_minutes  INT NOT NULL DEFAULT 15,
    actif          TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservations (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    code              VARCHAR(20) UNIQUE NOT NULL,
    plate_format      VARCHAR(10) NOT NULL DEFAULT 'STD',  -- clé vers les formats définis en PHP (config.php -> PLATE_FORMATS)
    plate_left        VARCHAR(6) NOT NULL,                 -- ex: "123"  (groupe de gauche)
    plate_right       VARCHAR(6) NOT NULL,                 -- ex: "4567" (groupe de droite)
    plate_digits      VARCHAR(12) NOT NULL,                -- ex: "1234567" — clé de matching (chiffres uniquement, sans تونس/TUN)
    owner_name        VARCHAR(100) NULL,
    phone             VARCHAR(20) NULL,
    forfait_id        INT NOT NULL,
    zone_id           INT NOT NULL,
    reservation_date  DATE NOT NULL,
    start_time        TIME NOT NULL,
    end_time          TIME NOT NULL,        -- calculé côté serveur (start_time + duree du forfait)
    grace_minutes     INT NOT NULL,
    price_tnd         DECIMAL(6,2) NOT NULL,
    status            ENUM('confirmed','checked_in','completed','expired','cancelled') NOT NULL DEFAULT 'confirmed',
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (forfait_id) REFERENCES forfaits(id),
    FOREIGN KEY (zone_id) REFERENCES zones(id),
    INDEX idx_plate_digits (plate_digits),
    INDEX idx_date (reservation_date, start_time)
) ENGINE=InnoDB;

-- ---- Seed : zones ----------------------------------------------------------
INSERT INTO zones (code, label, capacity) VALUES
    ('A', 'Zone A — Entrée principale', 40),
    ('B', 'Zone B — Niveau couvert', 25),
    ('C', 'Zone C — Longue durée', 30)
ON DUPLICATE KEY UPDATE label = VALUES(label), capacity = VALUES(capacity);

-- ---- Seed : forfaits (correspond aux 3 cartes de la page pricing) ---------
INSERT INTO forfaits (id, nom, type, duree_minutes, prix_tnd, grace_minutes, actif) VALUES
    (1, 'À l''heure', 'horaire', NULL, 2.50, 15, 1),
    (2, 'Journée complète', 'jour', 480, 15.00, 30, 1),
    (3, 'Longue durée (mensuel)', 'abonnement', NULL, NULL, 30, 1)
ON DUPLICATE KEY UPDATE nom = VALUES(nom);
