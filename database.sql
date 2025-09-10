-- Oppilaitoksen kurssienhallintajärjestelmän tietokanta
-- School Course Management System Database

-- Luo tietokanta
CREATE DATABASE IF NOT EXISTS kurssienhallinta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kurssienhallinta;

-- Käyttäjät (Users) - uusi taulu autentikaatiota varten
CREATE TABLE IF NOT EXISTS kayttajat (
    tunnus INT PRIMARY KEY AUTO_INCREMENT,
    kayttajanimi VARCHAR(50) UNIQUE NOT NULL,
    salasana_hash VARCHAR(255) NOT NULL,
    rooli ENUM('admin', 'opettaja', 'opiskelija') NOT NULL,
    etunimi VARCHAR(50) NOT NULL,
    sukunimi VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    aktiivinen BOOLEAN DEFAULT TRUE,
    salasana_vaihdettu BOOLEAN DEFAULT FALSE,
    salasana_luotu DATETIME DEFAULT CURRENT_TIMESTAMP,
    viimeisin_kirjautuminen DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Opettajat (Teachers)
CREATE TABLE IF NOT EXISTS opettajat (
    tunnus INT PRIMARY KEY AUTO_INCREMENT,
    kayttaja_tunnus INT,
    etunimi VARCHAR(50) NOT NULL,
    sukunimi VARCHAR(50) NOT NULL,
    aine VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kayttaja_tunnus) REFERENCES kayttajat(tunnus) ON DELETE SET NULL
);

-- Tilat (Rooms)
CREATE TABLE IF NOT EXISTS tilat (
    tunnus INT PRIMARY KEY AUTO_INCREMENT,
    nimi VARCHAR(100) NOT NULL,
    kapasiteetti INT NOT NULL CHECK (kapasiteetti > 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Opiskelijat (Students)
CREATE TABLE IF NOT EXISTS opiskelijat (
    tunnus INT PRIMARY KEY AUTO_INCREMENT,
    kayttaja_tunnus INT,
    opiskelijanumero VARCHAR(20) UNIQUE NOT NULL,
    etunimi VARCHAR(50) NOT NULL,
    sukunimi VARCHAR(50) NOT NULL,
    syntymapaiva DATE NOT NULL,
    vuosikurssi INT NOT NULL CHECK (vuosikurssi >= 1 AND vuosikurssi <= 3),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kayttaja_tunnus) REFERENCES kayttajat(tunnus) ON DELETE SET NULL
);

-- Kurssit (Courses)
CREATE TABLE IF NOT EXISTS kurssit (
    tunnus INT PRIMARY KEY AUTO_INCREMENT,
    nimi VARCHAR(200) NOT NULL,
    kuvaus TEXT,
    alkupaiva DATE NOT NULL,
    loppupaiva DATE NOT NULL,
    opettaja_tunnus INT NOT NULL,
    tila_tunnus INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (opettaja_tunnus) REFERENCES opettajat(tunnus) ON DELETE CASCADE,
    FOREIGN KEY (tila_tunnus) REFERENCES tilat(tunnus) ON DELETE CASCADE
);

-- Kurssikirjautumiset (Course Registrations)
CREATE TABLE IF NOT EXISTS kurssikirjautumiset (
    tunnus INT PRIMARY KEY AUTO_INCREMENT,
    opiskelija_tunnus INT NOT NULL,
    kurssi_tunnus INT NOT NULL,
    kirjautumispvm DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (opiskelija_tunnus) REFERENCES opiskelijat(tunnus) ON DELETE CASCADE,
    FOREIGN KEY (kurssi_tunnus) REFERENCES kurssit(tunnus) ON DELETE CASCADE,
    UNIQUE KEY (opiskelija_tunnus, kurssi_tunnus)
);

-- Indeksit suorituskyvyn parantamiseksi
CREATE INDEX idx_opettajat_aine ON opettajat(aine);
CREATE INDEX idx_kurssit_opettaja ON kurssit(opettaja_tunnus);
CREATE INDEX idx_kurssit_tila ON kurssit(tila_tunnus);
CREATE INDEX idx_kurssit_alkupaiva ON kurssit(alkupaiva);
CREATE INDEX idx_kirjautumiset_opiskelija ON kurssikirjautumiset(opiskelija_tunnus);
CREATE INDEX idx_kirjautumiset_kurssi ON kurssikirjautumiset(kurssi_tunnus);
CREATE INDEX idx_kayttajat_rooli ON kayttajat(rooli);
CREATE INDEX idx_kayttajat_kayttajanimi ON kayttajat(kayttajanimi);

-- Näkymät (Views) tietojen hakemiseen
CREATE VIEW kurssi_nakyma AS
SELECT 
    k.tunnus,
    k.nimi,
    k.kuvaus,
    k.alkupaiva,
    k.loppupaiva,
    CONCAT(o.etunimi, ' ', o.sukunimi) AS opettaja_nimi,
    t.nimi AS tila_nimi,
    t.kapasiteetti,
    COUNT(kk.opiskelija_tunnus) AS osallistujia
FROM kurssit k
JOIN opettajat o ON k.opettaja_tunnus = o.tunnus
JOIN tilat t ON k.tila_tunnus = t.tunnus
LEFT JOIN kurssikirjautumiset kk ON k.tunnus = kk.kurssi_tunnus
GROUP BY k.tunnus;

CREATE VIEW opiskelija_nakyma AS
SELECT 
    o.tunnus,
    o.opiskelijanumero,
    o.etunimi,
    o.sukunimi,
    o.syntymapaiva,
    o.vuosikurssi,
    COUNT(kk.kurssi_tunnus) AS kurssien_maara
FROM opiskelijat o
LEFT JOIN kurssikirjautumiset kk ON o.tunnus = kk.opiskelija_tunnus
GROUP BY o.tunnus;

CREATE VIEW opettaja_nakyma AS
SELECT 
    o.tunnus,
    o.etunimi,
    o.sukunimi,
    o.aine,
    COUNT(k.tunnus) AS kurssien_maara
FROM opettajat o
LEFT JOIN kurssit k ON o.tunnus = k.opettaja_tunnus
GROUP BY o.tunnus;

CREATE VIEW tila_nakyma AS
SELECT 
    t.tunnus,
    t.nimi,
    t.kapasiteetti,
    COUNT(DISTINCT k.tunnus) AS kurssien_maara,
    COUNT(kk.opiskelija_tunnus) AS osallistujia_yhteensa
FROM tilat t
LEFT JOIN kurssit k ON t.tunnus = k.tila_tunnus
LEFT JOIN kurssikirjautumiset kk ON k.tunnus = kk.kurssi_tunnus
GROUP BY t.tunnus;
