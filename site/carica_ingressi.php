<?php
// Connessione al database
$servername = "localhost";
$username = "root";
$password = "Riccardo.03";
$dbname = "palestra"; // Nome del tuo database

// Crea connessione
$conn = new mysqli($servername, $username, $password, $dbname);

// Controlla la connessione
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query per recuperare i dati dalla tabella ingresso_sala_pesi
$sql = "SELECT id_ingresso, cod_sala, cod_iscritto, data_ingresso FROM ingresso_sala_pesi ORDER BY data_ingresso DESC"; // Ordina per data
$result = $conn->query($sql);

// Controlla se ci sono risultati
if ($result->num_rows > 0) {
    // Crea gli elementi della lista per ogni ingresso
    while($row = $result->fetch_assoc()) {
        echo "<li><strong>Ingresso ID:</strong> " . $row['id_ingresso'] . " - <strong>Sala:</strong> " . $row['cod_sala'] . " - <strong>Iscritto:</strong> " . $row['cod_iscritto'] . " - <strong>Data:</strong> " . $row['data_ingresso'] . "</li>";
    }
} else {
    echo "<li>Nessun ingresso trovato</li>";
}

$conn->close();
?>
