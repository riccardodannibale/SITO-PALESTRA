-- DROP DATABASE se esiste (opzionale, per reinstallare da zero)
DROP DATABASE IF EXISTS fitness_studio;
CREATE DATABASE fitness_studio CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE fitness_studio;

-- Tabella abbonamenti
CREATE TABLE abbonamenti (
    id_abbonamento INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    durata_mesi INT NOT NULL,
    prezzo DECIMAL(8,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabella utenti
CREATE TABLE users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    id_abbonamento INT DEFAULT NULL,
    data_registrazione DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_abbonamento) REFERENCES abbonamenti(id_abbonamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabella user_abbonamenti per tracciare abbonamenti attivi e loro stato
CREATE TABLE user_abbonamenti (
    id_user_abbonamento INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    id_abbonamento INT NOT NULL,
    data_inizio DATE NOT NULL,
    data_scadenza DATE NOT NULL,
    stato VARCHAR(50) NOT NULL,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_abbonamento) REFERENCES abbonamenti(id_abbonamento) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabella corsi (con campo datetime_corso)
CREATE TABLE corsi (
    id_corso VARCHAR(50) PRIMARY KEY,
    nome_corso VARCHAR(100) NOT NULL,
    descrizione TEXT,
    durata_lezione INT NOT NULL, -- durata in minuti
    datetime_corso DATETIME NULL,
    posti_totali INT NOT NULL DEFAULT 20
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabella prenotazione
CREATE TABLE prenotazione (
    id_prenotazione INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    id_corso VARCHAR(50) NOT NULL,
    data_prenotazione DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_corso (id_user, id_corso),
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_corso) REFERENCES corsi(id_corso) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- NUOVA Tabella promo
CREATE TABLE promo (
    id_promo INT AUTO_INCREMENT PRIMARY KEY,
    titolo VARCHAR(100) NOT NULL,
    descrizione TEXT NOT NULL,
    data_inizio DATE NOT NULL,
    data_fine DATE NOT NULL,
    codice_sconto VARCHAR(50) UNIQUE,
    attiva TINYINT(1) NOT NULL DEFAULT 1 -- 1 = attiva, 0 = non attiva
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Esempio dati iniziali abbonamenti
INSERT INTO abbonamenti (tipo, durata_mesi, prezzo) VALUES
('Mensile', 1, 30.00),
('Trimestrale', 3, 80.00),
('Annuale', 12, 300.00);

-- Esempio utenti (password in chiaro per test, in produzione usare hash)
INSERT INTO users (username, password, email, is_admin, id_abbonamento) VALUES
('mario', 'mario', 'mario@example.com', 1, 1),
('luisa', 'luisa', 'luisa@example.com', 0, 2);

-- Esempio corsi (con datetime_corso)
INSERT INTO corsi (id_corso, nome_corso, descrizione, durata_lezione, datetime_corso, posti_totali) VALUES
('c1', 'Pilates', 'Corso di Pilates per tutti i livelli', 60, '2025-06-01 18:00:00', 15),
('c2', 'Zumba', 'Corso di danza fitness divertente', 45, '2025-06-02 17:00:00', 20),
('c3', 'Ginnastica Dolce', 'Esercizi dolci per la mobilità articolare', 50, '2025-06-03 09:00:00', 10);

-- Esempio abbonamento attivo per Luisa (id_user = 2)
INSERT INTO user_abbonamenti (id_user, id_abbonamento, data_inizio, data_scadenza, stato)
VALUES (2, 2, '2025-05-26', '2026-05-26', 'attivo');
