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

// Creazione del database se non esiste
$sqlCreateDB = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if (!$conn->query($sqlCreateDB)) {
    die("Errore nella creazione del database: " . $conn->error);
}

// Selezione del database
$conn->select_db($db_name);

// Percorso assoluto del file SQL da eseguire
$sqlFile = __DIR__ . '/../fitness_studio.sql';

// Lettura del contenuto del file SQL
$sqlContent = file_get_contents($sqlFile);
if ($sqlContent === false) {
    die("Errore nella lettura del file SQL.");
}

// Divisione in singole query
$queries = array_filter(array_map('trim', explode(";", $sqlContent)));

// Disattiva temporaneamente il controllo delle chiavi esterne
$conn->query("SET foreign_key_checks = 0");

// Esecuzione di ogni query
foreach ($queries as $query) {
    if (!empty($query)) {
        if (!$conn->query($query)) {
            echo "Errore eseguendo la query: " . $conn->error . "<br><pre>$query</pre><br>";
        }
    }
}

// Riattiva il controllo delle chiavi esterne
$conn->query("SET foreign_key_checks = 1");

// Messaggio di conferma
echo "Installazione completata con successo.";

// Chiude la connessione
$conn->close();
?>
