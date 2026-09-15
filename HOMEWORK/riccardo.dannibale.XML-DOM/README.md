# Homework XML-DOM – Gestione corsi in una piattaforma per palestra

## Nomi degli autori:
- Riccardo D'Annibale
- Francesco Sabella

## Repository GitHub personali:
- Francesco Sabella: https://github.com/Ollare33/LW-HOMEWORK3
- Riccardo D'Annibale: https://github.com/ricky2905/LW-HOMEWORK3

## Esercizi di riferimento nelle slide:
- XML2: parsing e manipolazione DOM in PHP
- Esercizi su lettura/scrittura XML, inserimento/rimozione nodi
- Validazione con DTD

## Descrizione del progetto:
Questa applicazione è il **terzo homework** del corso e rappresenta una **estensione XML/DOM** della piattaforma web per la palestra.

Dopo aver realizzato:
1. un sito statico (XHTML+CSS),
2. un sito dinamico (PHP+MySQL),
questa versione introduce la gestione dei **corsi e delle promozioni tramite file XML** e **manipolazione DOM con PHP**.

### Funzionalità aggiuntive con XML:
- Visualizzazione dei corsi letti da `corsi.xml`
- Visualizzazione delle promozioni da `promo.xml`
- Aggiunta dinamica di nuovi corsi via `aggiungi_corso.php` (DOM + XML)
- Cancellazione corsi da XML con `cancella_corso.php` (DOM + XML)
- Validazione file XML con DTD (`corsi.dtd` e `promo.dtd`)

Il sistema resta integrato con il database MySQL per la gestione utenti e prenotazioni, mentre i contenuti visuali come corsi e promo sono gestiti via XML.

---

## Tipologie di utenti e funzionalità

| Tipo Utente           | Email               | Password | Privilegi principali |
|-----------------------|---------------------|----------|-----------------------|
| Utente con abbonamento| luisa@example.com   | luisa    | Può visualizzare corsi/promozioni, prenotare corsi, accedere all’area riservata |
| Admin                 | mario@example.com   | mario    | Può aggiungere/cancellare corsi XML, visualizzare tutte le prenotazioni, gestire contenuti |

- Gli **utenti con abbonamento attivo** possono:
  - accedere all'area riservata (`stato_abbonamento.php`)
  - prenotare corsi (`prenota_corso.php`)
  - vedere lo stato del proprio abbonamento

- Gli **admin** possono:
  - aggiungere nuovi corsi XML tramite `aggiungi_corso.php`
  - cancellare corsi esistenti tramite `cancella_corso.php`
  - accedere a funzioni gestionali nascoste agli utenti normali

---

## Organizzazione dei file e cartelle:
Directory principale: `riccardo.dannibale.XML-DOM`

Contenuto:

riccardo.dannibale.XML-DOM/
│ README.txt
│ fitness_studio.sql
│
├── site/
│ ├── home_page.php
│ ├── login.php
│ ├── register.php
│ ├── logout.php
│ ├── promo.php
│ ├── stato_abbonamento.php
│ ├── prenota_corso.php
│ ├── aggiungi_corso.php
│ ├── cancella_corso.php
│ ├── leggi_corso.php
│ ├── install.php
│ ├── db.php
│ ├── Chi_siamo.php
│ ├── dati_generali.php
│ │
│ ├── dtd/
│ │ ├── corsi.dtd
│ │ └── promo.dtd
│ │
│ ├── xml/
│ │ ├── corsi.xml
│ │ └── promo.xml
│ │
│ ├── img/
│ │ └── (immagini corsi e promozioni)
│ │
│ └── style/
│ ├── style.css
│ ├── corsi.css
│ ├── promo.css
│ ├── Style_home_page.css
│ └── Chi_Siamo.css


---

## Database:
Il database utilizzato è `fitness_studio`, usato per:
- gestione utenti
- gestione abbonamenti
- prenotazione corsi

I **corsi** e le **promozioni** invece sono salvati in file XML (`corsi.xml`, `promo.xml`) e manipolati via **DOM in PHP**.

---

## Istruzioni per l’installazione:
1. Installare un server locale con PHP, MySQL e phpMyAdmin (es. XAMPP o MAMP)
2. Creare il database `fitness_studio` tramite phpMyAdmin
3. Importare il file `fitness_studio.sql`
4. Configurare `site/db.php` con le proprie credenziali
5. Posizionare la cartella `site/` nella directory di root del server web (es. `htdocs/`)
6. Accedere da browser a `home_page.php` e testare tutte le funzionalità

---

## Note:
- Il progetto è stato sviluppato **in collaborazione attiva**, con contributi condivisi e revisionati da entrambi i membri.
- Il file `corsi.xml` è conforme alla grammatica definita in `corsi.dtd` e viene aggiornato via PHP con DOMDocument.
- Il file `promo.xml` funziona analogamente per le promozioni.
- È stata mantenuta una minima base dati per utenti e prenotazioni, come da specifiche.

---
