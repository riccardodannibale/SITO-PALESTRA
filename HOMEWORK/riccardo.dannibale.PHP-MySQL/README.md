# Sito Web della Palestra

## Componenti del gruppo:
- Riccardo D'Annibale  
- Francesco Sabella

## Repository GitHub:
- [Francesco Sabella](https://github.com/Ollare33/LW-HOMEWORK2)  
- [Riccardo D'Annibale](https://github.com/ricky2905/LW-HOMEWORK2)

---

## Descrizione del progetto

Questo progetto è il **secondo homework** del corso, che estende il lavoro del primo.  
Il primo esercizio si concentrava sulla creazione di un sito statico con **XHTML 1.0** e **CSS**.  
In questa versione, sono stati aggiunti **PHP**, **MySQL** e **phpMyAdmin** per creare un sito dinamico e gestire dati utente e abbonamenti.

Il sito simula una piattaforma per una palestra, con funzionalità quali:  
- Autenticazione (login/registrazione)  
- Visualizzazione stato abbonamento (area riservata)  
- Visualizzazione promozioni e corsi  
- Prenotazione corsi (per utenti con abbonamento attivo)  
- Logout

---

## Funzionalità principali

- **Login/Register:** gestione degli utenti, verifica email duplicata e sessioni PHP.  
- **Stato Abbonamento:** mostra i dettagli (tipo, durata, prezzo) dell’abbonamento associato all’utente loggato.  
- **Prenotazione Corsi:** consente agli utenti con abbonamento attivo di prenotare corsi evitando duplicati.  
- **Promo:** pagina con offerte promozionali visibili a tutti.  
- **Corsi:** elenco dei corsi disponibili con descrizioni e immagini.  
- **Area riservata:** accessibile solo agli utenti autenticati, con controllo sessione.  
- **Logout:** termina la sessione utente.

---

## Tecnologie utilizzate

- **XHTML 1.0 Strict** con validazione W3C  
- **CSS** per styling separato e responsive di base  
- **PHP** per la logica lato server e gestione sessioni  
- **MySQL** per il database degli utenti, abbonamenti e prenotazioni  
- **phpMyAdmin 5.2.1** per la gestione del database in ambiente grafico  

---

## Struttura del progetto


README.md # Questo file con documentazione
fitness_studio.sql # Dump del database da importare
site/ # File e cartelle del sito
├── Chi_Siamo.html # Informazioni generali sulla palestra
├── corsi.html # Elenco corsi offerti
├── db.php # Connessione al database
├── home_page.php # Pagina principale
├── login.php # Form login
├── logout.php # Script di logout
├── promo.html # Pagina promozioni
├── register.php # Form registrazione
├── stato_abbonamento.php # Stato abbonamento utente (area riservata)
├── prenota_corso.php # Script per prenotazione corsi (area riservata)
├── install.php # Script per installazione/import DB (se presente)
├── img/ # Cartella immagini
└── style/ # Cartella CSS
├── style.css # Stile login, register, generico
├── promo.css # Stile pagina promo
├── corsi.css # Stile pagina corsi
└── style_home_page.css #Stile home page 
└── Chi_siamo.css  #Stile pagina chi siamo



---

## Installazione e Setup

1. **Prerequisiti:**  
   - Server web Apache o simile con PHP 7.x o superiore  
   - MySQL o MariaDB  
   - phpMyAdmin (consigliato per importazione DB)  

2. **Importare il database:**  
   - Aprire phpMyAdmin  
   - Creare un nuovo database (es. `fitness_studio`)  
   - Importare il file `fitness_studio.sql` dal repository  

3. **Configurare la connessione al DB:**  
   - Modificare il file `site/db.php` inserendo host, username, password e nome DB corretti  

4. **Posizionare i file:**  
   - Copiare la cartella `site/` nella root del server web o nel percorso configurato  
   - Assicurarsi che il server supporti PHP ed abbia accesso al DB  

5. **Testare il sito:**  
   - Aprire il browser all’indirizzo locale (es. `http://localhost/site/home_page.php`)  
   - Effettuare il login con utenti di test (vedi sotto)  
   - Navigare nelle varie sezioni  

---

## Utenti di test

| Tipo Utente           | Email              | Password |
|-----------------------|--------------------|----------|
| Utente con abbonamento| luisa@example.com   | luisa    |
| Utente senza abbonamento | mario@example.com   | mario    |

*Nota:* Durante i test, solo l’utente con abbonamento può prenotare corsi e visualizzare lo stato abbonamento.

---

## Note importanti

- Le password attualmente **non sono hashate** (da migliorare per sicurezza).  
- Controlli di validità e sicurezza sono minimi, in ambiente di test locale.  
- È necessario effettuare il login per accedere alle funzionalità riservate.  
- Le pagine XHTML sono validate con il W3C Validator per garantire conformità.  

---

## Ulteriori suggerimenti

- Per aggiungere un nuovo utente, eseguire INSERT nel DB o usare la pagina di registrazione.  
- Per aggiungere abbonamenti o corsi, modificare direttamente il DB tramite phpMyAdmin.  
- Per migliorare sicurezza, implementare hashing password con `password_hash()` in PHP.  
- Per l’installazione automatica, è possibile usare lo script `install.php`.  

---


