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

// Query per ottenere gli iscritti attivi (con abbonamento non scaduto)
$sql = "select distinct 
    i.codice_fiscale, 
    i.nome, 
    i.cognome, 
    CASE 
        WHEN isc.cod_pacchetto = 1 THEN 'Corsi' 
        WHEN isc.cod_pacchetto = 2 THEN 'Sala Pesi' 
        WHEN isc.cod_pacchetto = 3 THEN 'Mix' 
        ELSE 'Non attivo' 
    END AS tipo_abbonamento
FROM iscritto i
JOIN iscrizione isc ON i.codice_fiscale = isc.cod_iscritto
WHERE isc.data_fine IS NULL OR isc.data_fine >= CURDATE()
ORDER BY i.nome ASC;";


$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Crea gli elementi della lista per ogni iscritto
    while($row = $result->fetch_assoc()) {
        echo "<li><strong>Iscritto nome:</strong> " . $row['nome'] . " - <strong>cognome:</strong> " . $row['cognome'] . " - <strong>codice fiscale:</strong> ". $row['codice_fiscale'] ." - <strong>tipo abbonamento:</strong> ". $row['tipo_abbonamento'] ."</li>";
    }
} else {
    echo "<li>Nessun iscritto trovato</li>";
}

$conn->close();
?>
