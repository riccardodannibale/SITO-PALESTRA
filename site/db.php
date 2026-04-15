<?php
$host = 'localhost'; // o l'indirizzo del tuo server MySQL
$username = 'root';  // nome utente del database
$password = '';      // password del database
$dbname = 'palestra'; // nome del tuo database

// Crea la connessione
$conn = new mysqli($host, $username, $password, $dbname);

// Controlla se la connessione è riuscita
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
} else {
    //echo "Connessione al database riuscita!";
}
?>
