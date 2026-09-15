<?php
// Avvia la sessione per accedere alle variabili di sessione (es. id_user)
session_start();

// Include il file per la connessione al database
include 'db.php';

// Controlla se l'utente è loggato, altrimenti lo reindirizza alla pagina di login
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit();
}

// Recupera l'ID dell'utente dalla sessione
$user_id = $_SESSION['id_user'];

// Query SQL per ottenere l'abbonamento più recente dell'utente
$sql = "
    SELECT a.tipo,
           a.durata_mesi,
           a.prezzo,
           ua.data_inizio,
           ua.data_scadenza,
           ua.stato
    FROM user_abbonamenti ua
    JOIN abbonamenti a ON ua.id_abbonamento = a.id_abbonamento
    WHERE ua.id_user = ?
    ORDER BY ua.data_scadenza DESC
    LIMIT 1
";

// Prepara la query SQL in modo sicuro (previene SQL Injection)
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id); // Collega il parametro dell'utente
    $stmt->execute();                 // Esegue la query
    $stmt->store_result();           // Salva i risultati in memoria

    // Controlla se è stato trovato un abbonamento
    if ($stmt->num_rows === 1) {
        // Collega i risultati alle variabili PHP
        $stmt->bind_result(
            $tipo,
            $durata_mesi,
            $prezzo,
            $data_inizio,
            $data_scadenza,
            $stato
        );
        $stmt->fetch();

        // Calcola i giorni rimanenti prima della scadenza
        $oggi = new DateTime();                  // Data odierna
        $scadenza = new DateTime($data_scadenza); // Data di scadenza
        $intervallo = $oggi->diff($scadenza);    // Differenza tra le due date
        $giorni_rimanenti = ($oggi > $scadenza) ? 0 : $intervallo->days;
    } else {
        // Se non è stato trovato nessun abbonamento, mostra messaggi predefiniti
        $tipo = $durata_mesi = $prezzo = $data_inizio = $data_scadenza = $stato = "Nessun abbonamento attivo.";
        $giorni_rimanenti = 0;
    }

    // Chiude lo statement
    $stmt->close();
} else {
    // In caso di errore nella preparazione della query, mostra messaggio di errore
    die("Errore prepare: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <title>Stato Abbonamento</title>
    <!-- Collegamento al file CSS -->
    <link rel="stylesheet" href="style/style.css" />
</head>
<body>
<div class="container">
    <h2>Stato del tuo abbonamento</h2>

    <!-- Form di sola lettura con i dati dell'abbonamento -->
    <form>
        <label for="tipo_abbonamento">Tipo Abbonamento:</label>
        <input type="text" id="tipo_abbonamento" value="<?= htmlspecialchars($tipo) ?>" readonly />

        <label for="durata">Durata (mesi):</label>
        <input type="text" id="durata" value="<?= htmlspecialchars($durata_mesi) ?>" readonly />

        <label for="prezzo">Prezzo (€):</label>
        <input type="text" id="prezzo" value="<?= htmlspecialchars($prezzo) ?>" readonly />

        <label for="data_inizio">Data Inizio:</label>
        <input type="text" id="data_inizio" value="<?= htmlspecialchars($data_inizio) ?>" readonly />

        <label for="data_scadenza">Data Scadenza:</label>
        <input type="text" id="data_scadenza" value="<?= htmlspecialchars($data_scadenza) ?>" readonly />

        <label for="stato">Stato:</label>
        <input type="text" id="stato" value="<?= htmlspecialchars($stato) ?>" readonly />

        <label for="giorni_rimanenti">Giorni Rimanenti:</label>
        <input type="text" id="giorni_rimanenti" value="<?= $giorni_rimanenti ?>" readonly />
    </form>

    <!-- Link per tornare alla home page -->
    <p><a href="home_page.php">Torna alla Home</a></p>
</div>
</body>
</html>
