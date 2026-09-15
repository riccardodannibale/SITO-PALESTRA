<?php
// Avvia la sessione per accedere ai dati utente
session_start();

// Includi il file di connessione al database
include 'db.php';

// Imposta l'header della risposta HTTP per indicare che sarà un JSON
header('Content-Type: application/json');

// Recupera l'id dell'utente dalla sessione, se esiste, altrimenti null
$id_user = $_SESSION['id_user'] ?? null;

// Recupera l'id del corso inviato tramite POST, se esiste, altrimenti null
$id_corso = $_POST['id_corso'] ?? null;

// Verifica se l'utente è loggato (cioè se $id_user è valorizzato)
if (!$id_user) {
    // Risposta JSON di errore: login necessario
    echo json_encode(['success' => false, 'message' => 'Devi effettuare il login.']);
    exit;
}

// Verifica che l'id del corso sia fornito e sia un numero valido
if (!$id_corso || !is_numeric($id_corso)) {
    // Risposta JSON di errore: id corso non valido
    echo json_encode(['success' => false, 'message' => 'ID corso non valido.']);
    exit;
}
// Converti l'id corso in intero per sicurezza
$id_corso = (int)$id_corso;

// Controlla che l'utente abbia un abbonamento attivo non scaduto
$stmt = $conn->prepare("SELECT COUNT(*) FROM user_abbonamenti WHERE id_user = ? AND stato = 'attivo' AND data_scadenza >= CURDATE()");
if (!$stmt) {
    // Errore nella preparazione della query
    echo json_encode(['success' => false, 'message' => 'Errore nella preparazione della query.']);
    exit;
}
$stmt->bind_param("i", $id_user);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

// Se non ci sono abbonamenti attivi
if ($count == 0) {
    // Risposta JSON di errore: nessun abbonamento attivo
    echo json_encode(['success' => false, 'message' => 'Nessun abbonamento attivo.']);
    exit;
}

// Controlla se l'utente ha già prenotato questo corso
$stmt = $conn->prepare("SELECT COUNT(*) FROM prenotazione WHERE id_user = ? AND id_corso = ?");
if (!$stmt) {
    // Errore nella preparazione della query
    echo json_encode(['success' => false, 'message' => 'Errore nella preparazione della query.']);
    exit;
}
$stmt->bind_param("ii", $id_user, $id_corso);
$stmt->execute();
$stmt->bind_result($exists);
$stmt->fetch();
$stmt->close();

// Se la prenotazione esiste già
if ($exists > 0) {
    // Risposta JSON di errore: già prenotato
    echo json_encode(['success' => false, 'message' => 'Hai già prenotato questo corso.']);
    exit;
}

// Inserisce la nuova prenotazione nella tabella 'prenotazione'
$stmt = $conn->prepare("INSERT INTO prenotazione (id_user, id_corso, data_prenotazione) VALUES (?, ?, NOW())");
if (!$stmt) {
    // Errore nella preparazione della query
    echo json_encode(['success' => false, 'message' => 'Errore nella preparazione della query.']);
    exit;
}
$stmt->bind_param("ii", $id_user, $id_corso);

// Esegue la query di inserimento e controlla il risultato
if ($stmt->execute()) {
    // Prenotazione avvenuta con successo
    echo json_encode(['success' => true, 'message' => 'Prenotazione effettuata con successo!']);
} else {
    // Errore durante l'inserimento della prenotazione
    echo json_encode(['success' => false, 'message' => 'Errore durante la prenotazione.']);
}

// Chiude lo statement e la connessione al database
$stmt->close();
$conn->close();
?>
