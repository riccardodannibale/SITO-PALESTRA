<?php
// Avvia la sessione per poterla manipolare
session_start();

// Svuota l'array $_SESSION, cancellando tutte le variabili di sessione attive
$_SESSION = [];

// Distrugge la sessione corrente, eliminando i dati memorizzati sul server
session_destroy();

// Controlla se la gestione dei cookie di sessione è attiva
if (ini_get("session.use_cookies")) {
    // Recupera i parametri del cookie di sessione (path, domain, secure, httponly)
    $params = session_get_cookie_params();

    // Imposta un cookie con lo stesso nome della sessione ma con scadenza passata
    // così da cancellare il cookie di sessione lato client (browser)
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Dopo aver pulito la sessione e il cookie, reindirizza l'utente alla pagina home_page.php
header("Location: home_page.php");
exit;
