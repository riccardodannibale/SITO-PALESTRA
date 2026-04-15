<?php
// Connessione al database
$host = "localhost"; // Il tuo host
$user = "root";      // Il tuo username
$password = "Riccardo.03";      // La tua password
$dbname = "palestra"; // Il nome del database

// Creazione della connessione
$conn = new mysqli($host, $user, $password, $dbname);

// Verifica della connessione
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Query per recuperare i messaggi e la data di invio
$sql = "SELECT contenuto, data_invio FROM messaggi ORDER BY data_invio DESC LIMIT 5"; // Aggiunto 'data_invio'
$result = $conn->query($sql);

// Creare una lista di messaggi
$messaggi = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Salviamo sia il contenuto che la data di invio
        $messaggi[] = [
            'contenuto' => $row['contenuto'],
            'data_invio' => $row['data_invio']
        ];
    }
} else {
    $messaggi[] = "Non ci sono nuovi messaggi."; // Messaggio di default se non ci sono messaggi
}

$conn->close(); // Chiudi la connessione
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Studio</title>
    <link rel="stylesheet" href="style/styles_home_cliente.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">
                <img src="style/img/1.png" alt="Left Image" class="logo-image left">
                <span>BODY <span>FIT</span></span>
                <img src="style/img/2.png" alt="Right Image" class="logo-image right">
            </div>
            <hr class="title-underline">
            <ul>
                <li><a href="home_cliente.php">Home</a></li>
                <li><a href="promo_cliente.php">Prezzi</a></li>
                <li><a href="calendario_cliente.php">Calendario</a></li>
                <li><a href="location_cliente.php">Dove siamo</a></li>
                <li><a href="profilo_cliente.php">Profilo</a></li>
                <li><a href="#" onclick="openLogoutPopup();">Logout</a></li>
            </ul>
            <hr class="title-underline">
        </nav>
    </header>

    <div class="message-box">
        <h3>Messaggi dalla Palestra</h3>
        <?php foreach ($messaggi as $messaggio): ?>
            <div class="message">
                <!-- Pallino accanto al messaggio -->
                <span class="new-message-dot"></span>
                <p><strong>Messaggio:</strong> <?php echo $messaggio['contenuto']; ?></p>
                <p><strong>Data di Invio:</strong> <?php echo date("d-m-Y H:i", strtotime($messaggio['data_invio'])); ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Popup di conferma logout -->
    <div id="logout-popup" class="popup-overlay">
        <div class="popup-box">
            <h2>Sei sicuro di voler effettuare il logout?</h2>
            <div class="popup-buttons">
                <button class="btn confirm" onclick="confirmLogout();">Sì, esci</button>
                <button class="btn cancel" onclick="closeLogoutPopup();">Annulla</button>
            </div>
        </div>
    </div>

    <script>
        function openLogoutPopup() {
            document.getElementById("logout-popup").style.display = "flex";
            document.body.style.overflow = "hidden"; // Blocca lo scroll
        }

        function closeLogoutPopup() {
            document.getElementById("logout-popup").style.display = "none";
            document.body.style.overflow = "auto"; // Ripristina lo scroll
        }

        function confirmLogout() {
            window.location.href = "index.php"; // Reindirizza alla pagina di logout
        }
    </script>

    <!-- Footer -->
    <footer>
        <p>&copy; 2025 Body Fit Palestra | Sviluppato da <strong>Riccardo</strong></p>
    </footer>
</body>
</html>
