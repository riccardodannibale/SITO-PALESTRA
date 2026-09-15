# 🏋️‍♂️ Fitness Studio – Piattaforma Web Completa per Palestra

## 📂 Struttura del Progetto

```text
Fitness Studio
│
├── README.md                         # File corrente
│
├── APPLICAZIONE
│   ├── fitness_studio.sql            # Schema del database
│   ├── install.php                    # Script di installazione
│   │
│   └── site                         # Cartella principale dell'applicazione web
│       ├── admin_chat.php             # Interfaccia admin per chat private con utenti
│       ├── aggiungi_abbonato.php     # Aggiunge un nuovo abbonato
│       ├── aggiungi_comunicazione.php # Invia comunicazioni ufficiali agli utenti
│       ├── aggiungi_corso_base.php   # Crea un nuovo corso base
│       ├── aggiungi_faq.php           # Aggiunge una voce alle FAQ
│       ├── aggiungi_lezione.php      # Aggiunge una lezione a un corso
│       ├── aggiungi_messaggio_forum.php # Pubblica un messaggio nel forum
│       ├── audit_log.php              # Generazione/visualizzazione dei log di audit
│       ├── auto_responder.php         # Risposte automatiche per la messaggistica
│       ├── cancella_corso_base.php    # Elimina un corso base
│       ├── cancella_lezione.php       # Elimina una lezione
│       ├── check_new_messages.php     # Controlla nuovi messaggi tramite polling
│       ├── Chi_siamo.php              # Pagina informativa "Chi siamo"
│       ├── confirm_abbonamento.php    # Conferma abbonamento / pagamento
│       ├── corsi.php                  # Visualizzazione elenco / dettaglio corsi
│       ├── dati_generali.php          # Dati generali della palestra
│       ├── db.php                     # Connessione e funzioni di accesso al DB
│       ├── elimina_contenuto.php      # Rimuove contenuti
│       ├── elimina_faq.php             # Elimina una voce FAQ
│       ├── elimina_prenotazione.php   # Annulla una prenotazione
│       ├── faq.php                    # Visualizzazione e gestione FAQ
│       ├── get_chat_messages.php      # Recupera i messaggi di una chat
│       ├── home_page.php              # Homepage dell'applicazione
│       ├── invio_mess_admin.php       # Invio messaggio da admin
│       ├── invio_mess_utente.php      # Invio messaggio da utente
│       ├── login.php                  # Login utenti
│       ├── logout.php                 # Logout
│       ├── mark_messages_read.php     # Marca messaggi come letti
│       ├── mi_piace.php               # Gestione "mi piace" nei forum/commenti
│       ├── modifica_contenuto.php     # Modifica contenuti informativi
│       ├── modifica_corso_base.php    # Modifica dati corso
│       ├── modifica_faq.php           # Modifica FAQ
│       ├── modifica_lezione.php       # Modifica una lezione esistente
│       ├── password_dimenticata.php   # Recupero password
│       ├── prenota_lezione.php        # Prenotazione di una lezione
│       ├── profilo.php                # Area profilo utente
│       ├── profilo_pubblico.php       # Visualizzazione profilo pubblico
│       ├── promo.php                  # Gestione delle promozioni
│       ├── promuovi_utente.php        # Promozione di un utente ad admin
│       ├── register.php               # Registrazione nuovi utenti
│       ├── save_feedback.php          # Salvataggio feedback e recensioni
│       ├── superadmin.php             # Pannello riservato al superadmin
│       ├── toggle_admin.php            # Attiva/disattiva ruolo admin
│       └── valuta_commento.php        # Registrazione valutazioni/commenti forum
│
│       ├── backup
│       │   ├── reputazione.xml.bak_20250914_112730
│       │   ├── reputazione.xml.bak_20250914_112735
│       │   ├── reputazione.xml.bak_20250914_112740
│       │   └── reputazione.xml.bak_20250914_112743
│       │
│       ├── dtd
│       │   ├── abbonamenti.dtd
│       │   ├── admin_audit.dtd
│       │   ├── comunicazioni.dtd
│       │   ├── corsi_base.dtd
│       │   ├── faq.dtd
│       │   ├── feedback.dtd
│       │   ├── forum.dtd
│       │   ├── lezioni.dtd
│       │   ├── private_chat.dtd
│       │   ├── promo.dtd
│       │   ├── reputazione.dtd
│       │   ├── statistiche.dtd
│       │   └── user_abbonamenti.dtd
│       │
│       ├── icon
│       │   ├── area_riservata.png
│       │   ├── chi_siamo.png
│       │   ├── corsi.png
│       │   ├── faq.png
│       │   ├── home.png
│       │   ├── logout.png
│       │   ├── profilo.png
│       │   └── promo.png
│       │
│       ├── img
│       │   ├── 1-quadrante.png
│       │   ├── 2-quadrante.png
│       │   ├── 3-quadrante.png
│       │   ├── 4-quadrante.png
│       │   ├── allarme.png
│       │   ├── Funzionale.png
│       │   ├── GinnasticaDolce.png
│       │   ├── mappa.png
│       │   ├── Pilates.png
│       │   ├── promo1.png
│       │   ├── promo2.png
│       │   ├── promo3.png
│       │   └── Zumba.png
│       │
│       ├── logs
│       │
│       ├── style
│       │   ├── style_chat_admin.css
│       │   ├── style_Chi_Siamo.css
│       │   ├── style_corsi.css
│       │   ├── style_faq.css
│       │   ├── style_home_page.css
│       │   ├── style_login.css
│       │   ├── style_profilo.css
│       │   └── style_promo.css
│       │
│       └── xml
│           ├── abbonamenti.xml
│           ├── admin_audit.xml
│           ├── comunicazioni.xml
│           ├── corsi_base.xml
│           ├── faq.xml
│           ├── feedback.xml
│           ├── forum.xml
│           ├── lezioni.xml
│           ├── private_chat.xml
│           ├── promo.xml
│           ├── reputazione.xml
│           ├── statistiche.xml
│           └── user_abbonamenti.xml
│
├── HOMEWORK
│   └── riccardo.dannibale.PHP-MySQL
│       ├── fitness_studio.sql
│       ├── README.md
│       │
│       ├── site
│       │   ├── Chi_siamo.php
│       │   ├── corsi.php
│       │   ├── dati_generali.php
│       │   ├── db.php
│       │   ├── home_page.php
│       │   ├── install.php
│       │   ├── login.php
│       │   ├── logout.php
│       │   ├── prenota_corso.php
│       │   ├── promo.php
│       │   ├── register.php
│       │   └── stato_abbonamento.php
│       │
│       └── img
│           └── (risorse immagine per la consegna)
│
├── riccardo.dannibale.XHTML_CSS
│   ├── README.md
│   │
│   └── site
│       ├── Chi_Siamo.html
│       ├── corsi.html
│       ├── home_page.html
│       ├── promo.html
│       └── img
│           └── (risorse immagine per la consegna)
│
├── riccardo.dannibale.XML-DOM
│   ├── fitness_studio.sql
│   ├── README.md
│   │
│   ├── site
│   │   ├── aggiungi_corso.php
│   │   ├── cancella_corso.php
│   │   ├── cancella_prenotazione.php
│   │   ├── Chi_siamo.php
│   │   ├── dati_generali.php
│   │   ├── db.php
│   │   ├── home_page.php
│   │   ├── install.php
│   │   ├── leggi_corso.php
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── prenota_corso.php
│   │   ├── promo.php
│   │   ├── register.php
│   │   └── stato_abbonamento.php
│   │
│   ├── dtd
│   │   ├── corsi.dtd
│   │   └── promo.dtd
│   │
│   ├── img
│   │
│   ├── style
│   │
│   └── xml
│       ├── corsi.xml
│       └── promo.xml
│
├── ISTANZA GRP
│   └── istanza GRP.md
│
├── REPORT
│   ├── progressione progetto.png
│   ├── TESINA-LINGUAGGI PER IL WEB.pdf
│   │
│   └── screenshot
│       ├── aggiungi-abbonato.png
│       ├── aggiungi-comunicazione.png
│       ├── aggiungi-corso.png
│       ├── aggiungi-lezione.png
│       ├── cambia-password.png
│       ├── chi-siamo-admin.png
│       ├── chi-siamo-utenti.png
│       ├── corsi-admin.png
│       ├── corsi-utente-abbonato.png
│       ├── corsi-visitatore-utente-senza-abbonamento.png
│       ├── cronologia-contributi.png
│       ├── faq-admin.png
│       ├── faq-utente.png
│       ├── forum-admin.png
│       ├── forum-utente.png
│       ├── gestione-privilegi.png
│       ├── home-page-admin.png
│       ├── home-page-utente.png
│       ├── home-page-visitatore.png
│       ├── lista-prenotanti.png
│       ├── login.png
│       ├── modifica-lezione-corso.png
│       ├── nuova-comunicazione-admin.png
│       ├── private_chat_admin.png
│       ├── profilo-admin.png
│       ├── profilo-superadmin.png
│       ├── profilo-utente-abbonato.png
│       ├── promo-admin.png
│       ├── promo-utenti.png
│       ├── recupera-password.png
│       ├── sign-up.png
│       └── statistiche-avanzate-admin.png
│
└── XML DI PROVA
    └── xml
        ├── abbonamenti.xml
        ├── admin_audit.xml
        ├── comunicazioni.xml
        ├── corsi_base.xml
        ├── faq.xml
        ├── feedback.xml
        ├── forum.xml
        ├── lezioni.xml
        ├── private_chat.xml
        ├── promo.xml
        ├── reputazione.xml
        ├── statistiche.xml
        └── user_abbonamenti.xml
```

---

## ✅ Funzionalità Implementate

La piattaforma include:

* ✅ **Autenticazione & Gestione profili**

  * Registrazione
  * Login
  * Recupero password
  * Gestione dei ruoli admin/utente

* ✅ **Gestione corsi & lezioni**

  * CRUD completo
  * Prenotazione delle lezioni
  * Gestione delle iscrizioni

* ✅ **Gestione abbonamenti**

  * Utilizzo di XML
  * DTD dedicati

* ✅ **Sistema FAQ**

  * Creazione
  * Modifica
  * Eliminazione

* ✅ **Sistema comunicazioni interne**

  * Chat admin/utente
  * Auto-responder
  * Notifiche

* ✅ **Forum utenti**

  * Messaggi
  * Like
  * Valutazioni
  * Moderazione

* ✅ **Sistema valutazione corsi e feedback**

  * Feedback XML
  * Validazione tramite DTD

* ✅ **Statistiche avanzate**

  * Audit log
  * Statistiche XML

* ✅ **Documentazione completa**

  * Istanza dei requisiti
  * Relazione PDF
  * Screenshot delle interfacce

---

### 👤 Gestione Utenti

* Registrazione, login e recupero password
* Profilo personale con dati aggiornabili
* Ruoli:

  * **Utente**
  * **Amministratore**
  * **Superadmin**

---

### 🏋️‍♂️ Gestione Corsi & Lezioni

* Aggiunta, modifica ed eliminazione dei corsi
* Prenotazione delle lezioni
* Gestione delle iscrizioni
* Abbonamenti tramite XML + DTD dedicati

---

### 💬 Comunicazioni & FAQ

* FAQ dinamiche con CRUD completo
* Comunicazioni ufficiali da parte degli admin
* Messaggistica interna

  * Chat
  * Auto-responder
  * Notifiche

---

### ⭐ Feedback & Valutazioni

* Sistema di recensioni sui corsi
* Gestione dei feedback tramite XML
* Validazione tramite DTD
* Valutazione dei messaggi del forum
* Sistema **"Mi piace"**

---

### 📰 Contenuti Informativi

* Pagina Home
* Pagina "Chi siamo"
* Promozioni
* Dati generali della palestra

---

### 🧑‍🤝‍🧑 Forum Utenti

* Creazione di messaggi e interazioni tra utenti
* Possibilità di votare e commentare i post
* Moderazione da parte degli amministratori

---

### 📊 Statistiche Avanzate

* Audit log per il monitoraggio delle attività degli admin
* Statistiche relative a:

  * Prenotazioni
  * Corsi
  * Feedback degli utenti
* Dati esportati e salvati in XML

---

## 🔄 Progressione Logica dello Sviluppo

```text
HOMEWORK 1 : XHTML + CSS
        ↓
HOMEWORK 2 : PHP + MySQL
        ↓
HOMEWORK 3 : XML + DOM
        ↓
TESINA (FASE INTERMEDIA)
Gestione corsi, FAQ, chat, feedback, abbonamenti
        ↓
TESINA (FASE FINALE)
Forum + Statistiche avanzate
```

---

## 🚀 Avvio e Test del Progetto

### 1. Copiare l'applicazione

Copiare la cartella `APPLICAZIONE/` nella root del server web, ad esempio:

```text
htdocs/
└── APPLICAZIONE/
```

Per XAMPP è generalmente:

```text
C:\xampp\htdocs\
```

---

### 2. Configurare il database

Assicurarsi che il file:

```text
APPLICAZIONE/site/db.php
```

sia configurato correttamente con le credenziali del database.

---

### 3. Avviare il server locale

Avviare il server locale utilizzando, ad esempio:

* XAMPP
* MAMP

Assicurarsi che il server web e il database siano attivi.

---

### 4. Eseguire l'installazione

Aprire il browser e visitare:

```text
http://localhost/APPLICAZIONE/install.php
```

Lo script `install.php`:

* importa `fitness_studio.sql`;
* crea e inizializza i file XML nella cartella `site/xml/`.

È possibile eseguire l'installazione in modalità **clean** per generare XML vuoti/template, oppure utilizzare la modalità con dati di esempio.

---

### 5. Visitare la Home

Dopo l'installazione, la homepage è disponibile all'indirizzo:

```text
http://localhost/APPLICAZIONE/site/home_page.php
```

---

## 🔄 Backup & Ripristino

I backup automatici dei file XML vengono salvati nella cartella:

```text
APPLICAZIONE/site/backup/
```

I file di backup utilizzano un suffisso temporale nel formato:

```text
*.bak_YYYYMMDD_HHMMSS
```

Esempio:

```text
reputazione.xml.bak_20250914_112730
```

> ⚠️ Prima di eseguire operazioni distruttive in produzione, effettuare un backup manuale della cartella `site/xml/` e del database.

---

## 🤝 Contributi

1. Forkare la repository.
2. Creare un nuovo branch:

```bash
git checkout -b feature/nuova-funzionalita
```

3. Effettuare le modifiche.
4. Creare il commit.
5. Effettuare il push del branch:

```bash
git push origin feature/nuova-funzionalita
```

6. Aprire una **Pull Request**.

---

## 📞 Supporto

Per ricevere supporto è possibile:

* aprire una **Issue su GitHub**;
* contattare gli autori del progetto.

### Autori

* **Riccardo D’Annibale** – `ricky2905`
* **Francesco Sabella** – `Ollare33`
