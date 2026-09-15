<?php
// Avvia la sessione per gestire dati dell'utente loggato
session_start();

// Include il file per la connessione al database
include 'db.php';

// Controlla se l'utente è loggato, altrimenti lo reindirizza alla pagina di login
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit();
}

// Prendi l'id dell'utente dalla sessione
$user_id = $_SESSION['id_user'];

// Prepara una query per recuperare l'id_abbonamento associato all'utente
$stmt = $conn->prepare("SELECT id_abbonamento FROM users WHERE id_user = ?");
if (!$stmt) {
    // In caso di errore nella preparazione della query, termina con messaggio di errore
    die("Errore prepare: " . $conn->error);
}
$stmt->bind_param("i", $user_id); // Associa il parametro id_user
$stmt->execute(); // Esegue la query
$stmt->bind_result($id_abbonamento); // Associa il risultato a $id_abbonamento
$stmt->fetch(); // Recupera il risultato
$stmt->close(); // Chiude lo statement

// Se esiste un abbonamento associato all'utente
if ($id_abbonamento) {
    // Prepara una query per recuperare i dettagli dell'abbonamento
    $stmt = $conn->prepare("SELECT tipo, durata_mesi, prezzo FROM abbonamenti WHERE id_abbonamento = ?");
    if (!$stmt) {
        die("Errore prepare: " . $conn->error);
    }
    $stmt->bind_param("i", $id_abbonamento); // Associa l'id_abbonamento
    $stmt->execute();
    $stmt->store_result();

    // Se trova almeno un record
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($tipo, $durata, $prezzo); // Associa i risultati
        $stmt->fetch();
    } else {
        // Se non trova l'abbonamento, imposta messaggi di errore
        $tipo = $durata = $prezzo = "Abbonamento non trovato.";
    }
    $stmt->close();
} else {
    // Se l'utente non ha un abbonamento assegnato
    $tipo = $durata = $prezzo = "Nessun abbonamento assegnato.";
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <!-- Specifica la codifica dei caratteri -->
    <meta charset="UTF-8" />
    <title>Stato Abbonamento</title>
    <!-- Collegamento al file CSS per lo stile -->
    <link rel="stylesheet" href="style/style.css" />
</head>
<body>
<div class="container">
    <h2>Stato del tuo abbonamento</h2>

    <!-- Form che mostra i dettagli dell'abbonamento in campi di sola lettura -->
    <form>
        <label for="tipo_abbonamento">Tipo Abbonamento:</label>
        <input type="text" id="tipo_abbonamento" value="<?= htmlspecialchars($tipo) ?>" readonly />

        <label for="durata">Durata (mesi):</label>
        <input type="text" id="durata" value="<?= htmlspecialchars($durata) ?>" readonly />

        <label for="prezzo">Prezzo (€):</label>
        <input type="text" id="prezzo" value="<?= htmlspecialchars($prezzo) ?>" readonly />
    </form>

    <!-- Link per tornare alla home page -->
    <p><a href="home_page.php">Torna alla Home</a></p>
</div>
</body>
</html>
