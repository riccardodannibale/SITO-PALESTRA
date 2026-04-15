<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dove Siamo | Body Fit</title>
    <link rel="stylesheet" href="style/styles_location_cliente.css">
    
    <!-- Sostituisci con la tua chiave API -->
    <script async defer src="" async defer></script>
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
    <h1>Dove siamo</h1>

    <div class="location-info">
        <!-- Contenitore dei blocchi separati -->
        <div class="contact-info">
            <div class="info-block">
                <h2>Telefono</h2>
                <p><strong>Telefono:</strong> +39 012 345 6789</p>
            </div>
            <div class="info-block">
                <h2>Email</h2>
                <p><strong>Email:</strong> info@bodyfit.com</p>
            </div>
            <div class="info-block">
                <h2>Indirizzo</h2>
                <p><strong>Indirizzo:</strong> Via Esempio, 123, 00100 Roma (RM), Italia</p>
            </div>
        </div>

        <!-- La mappa sarà quadrata ora -->
        <div id="map"></div>
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
<footer>
    <p>&copy; 2025 Body Fit Palestra | Sviluppato da <strong>Riccardo</strong></p>
</footer>

<script>
    function initMap() {
        var location = { lat: 41.58968431671539, lng: 12.635202139684328}; 
        var map = new google.maps.Map(document.getElementById("map"), {
            zoom: 15,
            center: location,
        });
        var marker = new google.maps.Marker({
            position: location,
            map: map,
            title: "Body Fit Palestra"
        });
    }
</script>

</body>
</html>
