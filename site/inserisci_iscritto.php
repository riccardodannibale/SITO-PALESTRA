<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once "db.php";

alert("CF: "+$_SESSION['codice_fiscale']);
exit();

// Verifica che il dipendente sia loggato
if (!isset($_SESSION['codice_fiscale'])) {
    http_response_code(403);
    echo "Accesso negato.";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Recupera e assegna i dati della tabella 'iscritto'
    $codice_fiscale = $_POST['codice_fiscale'] ?? '';
    $nome           = $_POST['nome'] ?? '';
    $cognome        = $_POST['cognome'] ?? '';
    $data_nascita   = $_POST['data_nascita'] ?? '';
    $email          = $_POST['email'] ?? '';
    $telefono       = $_POST['telefono'] ?? '';
    $indirizzo      = $_POST['indirizzo'] ?? '';

    // Recupera i dati della tabella 'iscrizione'
    $cod_pacchetto   = isset($_POST['cod_pacchetto']) ? (int)$_POST['cod_pacchetto'] : 0;
    $data_iscrizione = $_POST['data_iscrizione'] ?? '';
    $data_fine       = $_POST['data_fine'] ?? '';
    $prezzo_finale   = isset($_POST['prezzo_finale']) ? floatval($_POST['prezzo_finale']) : 0.0;

    // Il codice del receptionist (dipendente loggato)
    $cod_receptionist = $_SESSION['codice_fiscale'];

    // Inserimento nella tabella 'iscritto'
    $stmtIscritto = $conn->prepare("INSERT INTO iscritto (codice_fiscale, nome, cognome, data_nascita, email, telefono, indirizzo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    alert("1");
	if (!$stmtIscritto) {
        http_response_code(500);
        echo "Errore nella preparazione della query per l'iscritto: " . $conn->error;
        exit();
    }
    $stmtIscritto->bind_param("sssssss", $codice_fiscale, $nome, $cognome, $data_nascita, $email, $telefono, $indirizzo);
    if (!$stmtIscritto->execute()) {
        http_response_code(500);
        echo "Errore nell'inserimento dell'iscritto: " . $stmtIscritto->error;
        $stmtIscritto->close();
        exit();
    }
    $stmtIscritto->close();

    // Inserimento nella tabella 'iscrizione'
    $stmtIscrizione = $conn->prepare("INSERT INTO iscrizione (cod_pacchetto, cod_receptionist, cod_iscritto, data_iscrizione, data_fine, prezzo_finale) VALUES (?, ?, ?, ?, ?, ?)");
    alert("2");
    if (!$stmtIscrizione) {
        // In caso di errore nella preparazione della query, elimina l'iscritto inserito
        $conn->query("DELETE FROM iscritto WHERE codice_fiscale = '" . $conn->real_escape_string($codice_fiscale) . "'");
        http_response_code(500);
        echo "Errore nella preparazione della query per l'iscrizione: " . $conn->error;
        exit();
    }
    // Il primo parametro (cod_pacchetto) è un intero, pertanto usiamo "i" per il binding.
    $stmtIscrizione->bind_param("issssd", $cod_pacchetto, $cod_receptionist, $codice_fiscale, $data_iscrizione, $data_fine, $prezzo_finale);
    if (!$stmtIscrizione->execute()) {
        // In caso di errore, elimina l'iscritto per mantenere la coerenza
        $conn->query("DELETE FROM iscritto WHERE codice_fiscale = '" . $conn->real_escape_string($codice_fiscale) . "'");
        http_response_code(500);
        echo "Errore nell'inserimento dell'iscrizione: " . $stmtIscrizione->error;
        $stmtIscrizione->close();
        exit();
    }
    $conn->query("COMMIT;");

    $stmtIscrizione->close();

    exit();
} else {
    http_response_code(400);
    echo "Richiesta non valida.";
    exit();
}

$conn->close();
?>
