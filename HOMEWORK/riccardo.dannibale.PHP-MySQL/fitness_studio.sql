-- DROP DATABASE se esiste (opzionale, per reinstallare da zero)
DROP DATABASE IF EXISTS fitness_studio;
CREATE DATABASE fitness_studio CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE fitness_studio;

-- Tabella abbonamenti (creata prima perché referenziata da altre tabelle)
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

-- Tabella corsi
CREATE TABLE corsi (
    id_corso INT AUTO_INCREMENT PRIMARY KEY,
    nome_corso VARCHAR(100) NOT NULL,
    descrizione TEXT,
    durata_lezione INT NOT NULL, -- durata in minuti
    posti_totali INT NOT NULL DEFAULT 20
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabella prenotazione
CREATE TABLE prenotazione (
    id_prenotazione INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    id_corso INT NOT NULL,
    data_prenotazione DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_corso (id_user, id_corso),
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_corso) REFERENCES corsi(id_corso) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Esempio dati iniziali abbonamenti
INSERT INTO abbonamenti (tipo, durata_mesi, prezzo) VALUES
('Mensile', 1, 30.00),
('Trimestrale', 3, 80.00),
('Annuale', 12, 300.00);

-- Esempio utenti (password in chiaro per test, in produzione hashare)
INSERT INTO users (username, password, email, id_abbonamento) VALUES
('mario', 'mario', 'mario@example.com', 1),
('luisa', 'luisa', 'luisa@example.com', 2);

-- Esempio corsi
INSERT INTO corsi (nome_corso, descrizione, durata_lezione, posti_totali) VALUES
('Pilates', 'Corso di Pilates per tutti i livelli', 60, 15),
('Zumba', 'Corso di danza fitness divertente', 45, 20),
('Ginnastica Dolce', 'Esercizi dolci per la mobilità articolare', 50, 10);

-- Luisa (id_user = 2), abbonamento Trimestrale (id_abbonamento = 2)
INSERT INTO user_abbonamenti (id_user, id_abbonamento, data_inizio, data_scadenza, stato)
VALUES (2, 3, '2025-07-07', '2026-07-07', 'attivo');