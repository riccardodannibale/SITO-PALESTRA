# Gestione di una piattaforma web per una palestra  
**Riccardo D’Annibale – (collega università)**  
**GRP – Corso di Linguaggi per il Web**

## Descrizione generale
Il progetto consiste nello sviluppo di un sito web per la gestione dei servizi offerti da PALESTRW, una palestra moderna e interattiva. L'applicativo prevede sia sezioni pubbliche accessibili a tutti, sia aree riservate con funzionalità specifiche in base alla tipologia di utente.

## Tipologie di utenti previsti:
    -Visitatore

    -Utente registrato (senza abbonamento)

    -Utente abbonato

    -Amministratore

## Modello dati utente:

Ogni utente è identificato da:

    -username

    -password

    -email

    -is_admin

    -indirizzo

    -numero di telefono

    Struttura e funzionalità principali

## Sezioni accessibili a tutti (Visitatori):

    -Visualizzazione delle comunicazioni generali della palestra

    -Elenco delle promozioni attive

    -Panoramica dei corsi disponibili

    -Sezione informativa con FAQ

## Utenti registrati (senza abbonamento attivo):

    -Accesso a tutte le sezioni pubbliche

    -Accesso all’area personale con possibilità di modificare la password

    -Visualizzazione dello stato dell’abbonamento (non attivo)

## Utenti abbonati:

    -Tutte le funzionalità del visitatore e dell’utente registrato

    -Prenotazione delle lezioni dei corsi

    -Consultazione dello storico delle prenotazioni

    -Valutazione delle lezioni frequentate (scala da 1 a 5, con possibilità di aggiungere un commento)

    -Valutazione delle valutazioni inserite da altri utenti abbonati (non le proprie), secondo due scale da 1 a 5: Utilità del commento e Accordo con la valutazione

    -Accesso a una chat privata con lo staff

    -Visualizzazione dello stato dettagliato dell’abbonamento

    -Consultazione e modifica del proprio profilo personale, compresi i dati anagrafici e lo stato della reputazione

    -Forum pubblico dove postare osservazioni generali o proposte di nuovi corsi

## Amministratore (flag is_admin nella tabella utenti)
    Tutte le funzionalità precedenti

    -Gestione dei messaggi pubblici (comunicazioni generali)

    -Visualizzazione degli utenti con abbonamento attivo

    -Aggiunta manuale di nuovi abbonati

    -Chat privata con gli utenti abbonati

    -Gestione dei corsi (aggiunta, modifica, eliminazione delle lezioni)

    -Statistiche sui feedback ricevuti: media dei voti, numero di valutazioni, andamento generale

    -Gestione delle promozioni (aggiunta, modifica, eliminazione)

    -Gestione delle FAQ (aggiunta, modifica, eliminazione)

    -Visione della reputazione degli utenti abbonati, utile per monitorare il coinvolgimento e la qualità dei contributi nel forum

   
   
## Sistema di feedback:

Le lezioni possono essere valutate con un punteggio da 1 (esperienza non soddisfacente) a 5 (molto soddisfacente), con la possibilità di lasciare anche un commento scritto.
Gli utenti abbonati possono inoltre valutare le valutazioni degli altri utenti (non le proprie) secondo due criteri, entrambi su scala 1–5:

    -Utilità del commento

    -Accordo con la valutazione

## Reputazione degli utenti:
Ogni utente abbonato ha un punteggio di reputazione, calcolato in base al numero e alla qualità delle valutazioni ricevute dai propri commenti nel forum e nei feedback alle lezioni. Questo sistema premia la partecipazione utile e costruttiva, rendendo più visibili i contributi degli utenti più apprezzati.

## Gestione delle proposte utenti:
Gli utenti abbonati possono proporre nuovi corsi o porre domande nel forum pubblico.
L'amministratore, sulla base delle richieste più ricorrenti, può decidere di:
    -Aggiungere un nuovo corso al calendario
    -Inserire una nuova voce nella sezione FAQ (domanda + risposta)

## Promozioni:
Le promozioni consistono in offerte speciali e sconti su abbonamenti, pacchetti stagionali, corsi intensivi o eventi specifici.
Il loro obiettivo è incentivare la partecipazione degli utenti, fidelizzare i clienti e aumentare la visibilità dei servizi offerti.
