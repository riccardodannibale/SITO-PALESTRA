<?php
// Includi la connessione al database
include('db.php');

// Avvia la sessione per verificare se l'utente è loggato
session_start();

// Verifica se l'utente è loggato
if (!isset($_SESSION['email'])) {
    header('Location: login_cliente.php'); // Redirigi alla pagina di login se non è loggato
    exit;
}

// Recupera l'email dell'utente loggato dalla sessione
$email = $_SESSION['email']; 

// Prepara la query per trovare l'iscritto con la stessa email nella tabella 'iscritto'
$sql = "SELECT * FROM iscritto WHERE email = ?";
$stmt = $conn->prepare($sql);

// Verifica se la query è stata preparata correttamente
if (!$stmt) {
    die("Errore nella preparazione della query: " . $conn->error);
}

// Lega il parametro (email)
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

// Verifica se l'utente è iscritto
$utente = $result->num_rows > 0 ? $result->fetch_assoc() : null;

// Logica per il logout
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    session_unset();
    session_destroy();
    header('Location: login_cliente.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/styles_promo_cliente.css">
    <title>Promo</title>
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
<main>
    <div class="image-container">
        <img src="style/img/4.png" alt="Immagine grande" class="image">
        <img src="style/img/3.jpg" alt="Immagine piccola 1" class="image">
        <img src="style/img/5.jpeg" alt="Immagine piccola 2" class="image">
    </div>
</main>

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
        document.body.style.overflow = "hidden";
    }

    function closeLogoutPopup() {
        document.getElementById("logout-popup").style.display = "none";
        document.body.style.overflow = "auto";
    }

    function confirmLogout() {
        window.location.href = "promo_cliente.php?logout=true";
    }
</script>

<footer>
    <p>&copy; 2025 Body Fit Palestra | Sviluppato da <strong>Riccardo</strong></p>
</footer>
</body>
</html>
