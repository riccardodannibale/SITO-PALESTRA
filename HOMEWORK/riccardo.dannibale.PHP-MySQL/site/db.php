<?php
// Include il file 'dati_generali.php' una sola volta, che contiene
// le credenziali per la connessione al database (host, user, password, nome del DB)
require_once 'dati_generali.php';

// Crea una nuova connessione al database MySQL usando l'estensione MySQLi
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

// Verifica se c'è stato un errore nella connessione
if ($conn->connect_error) {
    // In caso di errore, termina lo script e mostra un messaggio
    die("Connessione fallita: " . $conn->connect_error);
}
?>
