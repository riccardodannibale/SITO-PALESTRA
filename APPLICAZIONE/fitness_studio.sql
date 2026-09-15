DROP DATABASE IF EXISTS fitness_studio;
CREATE DATABASE fitness_studio CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE fitness_studio;

CREATE TABLE users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    password_temp TINYINT(1) DEFAULT 0,
    force_pw_change TINYINT(1) DEFAULT 0,
    indirizzo VARCHAR(255),
    telefono VARCHAR(50),
    reputazione INT DEFAULT 0
);

INSERT INTO users (id_user, username, password, email, is_admin, password_temp, force_pw_change, indirizzo, telefono, reputazione) VALUES
(1, 'mario', '$2y$10$RkDMVeMN0.oe65IH0d8NuOZy0Mk678yyvdvnDeosvEqZ6tuxpjNMO', 'mario@example.com', 1, 0, 0, 'Via Roma 1, 00100 Roma RM', '+39 06 12345678', 0),
(2, 'luisa', '$2y$10$w.1iy.hD2lV1Znjoql2uCOJ4CC2Wsj6v9b4sRGgq0k0mVaqTvLgZm', 'luisa@example.com', 0, 0, 0, 'Corso Italia 10, 20100 Milano MI', '+39 02 23456789', 0),
(3, 'gianni', '$2y$10$P9FNJkB.RxKB.Nqs31BySunPTJ6xVvlk1lbgWj5jzXe9N4TaRtWoW', 'gianni@example.com', 0, 0, 0, 'Piazza Garibaldi 5, 80100 Napoli NA', '+39 081 3456789', 0),
(4, 'sara', '$2y$10$y4DPpUbT3oX2RkikRxReGu8gouvri3zLDzfvxyD6Eqpm2P5fS7FU2', 'sara@example.com', 0, 0, 0, 'Via Verdi 12, 16100 Genova GE', '+39 010 4567890', 0),
(5, 'alice', '$2y$10$QMnmslL0K8ZL6JykOA5B7epXlkdOJoLM7sdZbdUweyidsf2gbrXWa', 'alice@example.com', 0, 0, 0, 'Via Dante 7, 50100 Firenze FI', '+39 055 5678901', 0),
(6, 'bob', '$2y$10$raUKGlcXduj83YNtvFqBhuJdBeEemS50PgydCXK3OkQA54cHGvKum', 'bob@example.com', 0, 0, 0, 'Lungomare 3, 90100 Palermo PA', '+39 091 6789012', 0),
(7, 'carla', '$2y$10$Vyl/NR8JImObUczxfNUgHOK.QPiPg9vI0Fn0/vrlckluCmrk54QKW', 'carla@example.com', 0, 0, 0, 'Via XX Settembre 20, 70100 Bari BA', '+39 080 7890123', 0),
(8, 'dario', '$2y$10$8pfrqKgjiK.06DCpUWxCnOvtxd.46Rd/0nvI8eQv2o5yTcgOCM0nG', 'dario@example.com', 0, 0, 0, 'Piazza Duomo 4, 09100 Cagliari CA', '+39 070 8901234', 0),
(9, 'admin', '$2y$10$.LurbeIqN3TTFxinKpIvwuna1IT5.3pkRemFsMGJ783Xd//1u.jNu', 'admin@gym.it', 1, 0, 0, 'Via Libertà 99, 95100 Catania CT', '+39 095 9012345', 0),
(10,'root','$2a$12$3oOOHPcHVgkaoeaegeUa9eiMgGY/3gvgSxhF2HoggKGpeMgldIjIi','root@email.com',1,0,0,'...','...',0);