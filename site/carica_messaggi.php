<?php
$host = "localhost";
$user = "root";
$password = "Riccardo.03";
$dbname = "palestra";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

$sql = "SELECT contenuto, DATE_FORMAT(data_invio, '%d/%m/%Y %H:%i') as data FROM messaggi ORDER BY data_invio DESC LIMIT 5";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<p><strong>" . $row['data'] . ":</strong> " . htmlspecialchars($row['contenuto']) . "</p>";
    }
} else {
    echo "<p>Nessun messaggio disponibile.</p>";
}

$conn->close();
?>
