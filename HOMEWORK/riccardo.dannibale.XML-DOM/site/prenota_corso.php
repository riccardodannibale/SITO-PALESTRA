<?php
session_start(); // Avvia la sessione per accedere a variabili come $_SESSION

header('Content-Type: application/json'); // Imposta il tipo di contenuto della risposta come JSON

include __DIR__.'/db.php'; // Include il file di connessione al database

// Recupera l'ID utente dalla sessione
$id_user = $_SESSION['id_user'] ?? null;

// Se l'utente non è autenticato, ritorna errore JSON
if (!$id_user) {
    echo json_encode(['success' => false, 'message' => 'Devi fare login.']);
    exit;
}

// CONTROLLO ABBONAMENTO ATTIVO
$stmt = $conn->prepare("
  SELECT COUNT(*) FROM user_abbonamenti 
  WHERE id_user = ? AND stato = 'attivo' AND data_scadenza >= CURDATE()
");
// Verifica che l'abbonamento sia attivo e non scaduto
$stmt->bind_param("i", $id_user);
$stmt->execute();
$stmt->bind_result($cnt);
$stmt->fetch();
$stmt->close();

// Se non ci sono abbonamenti attivi, blocca la prenotazione
if (!$cnt) {
    echo json_encode(['success' => false, 'message' => 'Abbonamento non attivo.']);
    exit;
}


// CONTROLLO DOPPIA PRENOTAZIONE
$id_corso = $_POST['id_corso'] ?? ''; // ID del corso da prenotare

$stmt = $conn->prepare("
    SELECT 1 FROM prenotazione WHERE id_user = ? AND id_corso = ?
");
// Verifica se l'utente ha già prenotato questo corso
$stmt->bind_param("is", $id_user, $id_corso);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows) {
    echo json_encode(['success' => false, 'message' => 'Hai già prenotato.']);
    exit;
}
$stmt->close();

// INSERIMENTO PRENOTAZIONE
$stmt = $conn->prepare("
  INSERT INTO prenotazione (id_user, id_corso) VALUES (?, ?)
");
// Inserisce una nuova riga nella tabella prenotazione
$stmt->bind_param("is", $id_user, $id_corso);
$ok = $stmt->execute(); // Salva se l'inserimento è riuscito
$stmt->close();


// RISPOSTA FINALE
echo json_encode([
  'success' => (bool)$ok,
  'message' => $ok ? 'Prenotazione effettuata!' : 'Errore in prenotazione.'
]);
