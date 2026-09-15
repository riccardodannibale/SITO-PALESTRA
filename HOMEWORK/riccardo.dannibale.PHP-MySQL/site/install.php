<?php
// Include il file con i parametri per la connessione al database
// Deve contenere: $db_host, $db_user, $db_password, $db_name
include 'dati_generali.php';

// Connessione al server MySQL senza selezionare ancora il database
$conn = new mysqli($db_host, $db_user, $db_password);

// Controllo errori nella connessione
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Query per creare il database se non esiste, con charset UTF-8 (utf8mb4 per compatibilità completa Unicode)
$sqlCreateDB = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";

// Esecuzione della query di creazione database
if (!$conn->query($sqlCreateDB)) {
    die("Errore nella creazione del database: " . $conn->error);
}

// Selezione del database da utilizzare
$conn->select_db($db_name);

// Percorso assoluto del file SQL da eseguire (contenente struttura e dati del DB)
$sqlFile = __DIR__ . '/../fitness_studio.sql';

// Legge il contenuto del file SQL
$sqlContent = file_get_contents($sqlFile);

// Se la lettura fallisce, interrompe lo script
if ($sqlContent === false) {
    die("Errore nella lettura del file SQL.");
}

// Suddivide il contenuto del file in singole query, rimuove spazi vuoti e filtra le righe vuote
$queries = array_filter(array_map('trim', explode(";", $sqlContent)));

// Disattiva temporaneamente il controllo delle chiavi esterne
$conn->query("SET foreign_key_checks = 0");

// Esecuzione di ogni query una per una
foreach ($queries as $query) {
    if (!empty($query)) {
        if (!$conn->query($query)) {
            // Se c'è un errore, lo mostra e stampa anche la query incriminata
            echo "Errore eseguendo la query: " . $conn->error . "<br><pre>$query</pre><br>";
        }
    }
}

// Riattiva il controllo delle chiavi esterne
$conn->query("SET foreign_key_checks = 1");

// Messaggio di conferma finale
echo "Installazione completata con successo.";

// Chiude la connessione al database
$conn->close();
?>
