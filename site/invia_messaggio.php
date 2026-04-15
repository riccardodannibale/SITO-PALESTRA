<?php
session_start();
include('db.php');

// Verifica se l'utente è loggato
if (!isset($_SESSION['email'])) {
    echo "Utente non autenticato.";
    exit();
}

$email = $_SESSION['email'];

// Recupera il codice fiscale del dipendente dalla tabella "dipendente"
$stmt = $conn->prepare("SELECT codice_fiscale FROM dipendente WHERE email = ?");
if (!$stmt) {
    echo "Errore nella preparazione della query: " . $conn->error;
    exit();
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo "Dipendente non trovato.";
    exit();
}

$row = $result->fetch_assoc();
$cod_dipendente = $row['codice_fiscale'];
$stmt->close();

// Verifica che il contenuto del messaggio sia presente e non vuoto
if (!isset($_POST['contenuto']) || trim($_POST['contenuto']) === "") {
    echo "Il messaggio non può essere vuoto.";
    exit();
}

$contenuto = trim($_POST['contenuto']);

// Inserisce il messaggio nella tabella "messaggi"
// Utilizziamo NOW() per salvare la data e l'ora correnti
$stmt = $conn->prepare("INSERT INTO messaggi (contenuto, data_invio, cod_dipendente) VALUES (?, NOW(), ?)");
if (!$stmt) {
    echo "Errore nella preparazione della query: " . $conn->error;
    exit();
}
$stmt->bind_param("ss", $contenuto, $cod_dipendente);

if ($stmt->execute()) {
    echo "Messaggio inviato con successo!";
} else {
    echo "Errore nell'invio del messaggio: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
