<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dove Siamo | Body Fit</title>
  <!-- Collega il file CSS esterno -->
  <link rel="stylesheet" href="style/styles_location_dipendente.css">
  
  <!-- jQuery per AJAX e gestione del DOM -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  
  <!-- Google Maps API (sostituisci YOUR_API_KEY con la tua chiave effettiva) -->
  <script src="" async defer></script>
</head>
<body>
  <header>
    <nav>
      <div class="logo">
        <img src="style/img/1.png" alt="Left Image" class="logo-image left">
        <span>BODY <span>FIT</span></span>
        <img src="style/img/2.png" alt="Right Image" class="logo-image right">
      </div>
      <!-- Bottone hamburger per il menu a tendina -->
      <div class="menu-toggle-container">
        <div class="menu-toggle" id="menu-toggle">
          <span></span>
          <span></span>
          <span></span>
        </div>
        <span class="menu-text">Controllo ingressi palestra</span>
      </div>
      <hr class="title-underline">
      <ul>
        <li><a href="home_dipendente.php">Home</a></li>
        <li><a href="promo_dipendente.php">Prezzi</a></li>
        <li><a href="calendario_dipendente.php">Calendario</a></li>
        <li><a href="location_dipendente.php">Dove siamo</a></li>
        <li><a href="profilo_dipendente.php">Profilo</a></li>
        <!-- Il link Logout richiama la funzione che apre il popup -->
        <li><a href="#" onclick="openLogoutPopup();">Logout</a></li>
      </ul>
      <hr class="title-underline">
      <!-- Menu a scomparsa -->
      <div id="side-menu">
        <div class="menu-content">
          <!-- Lista degli ingressi (popolata via AJAX) -->
          <ul id="lista-ingressi">
            <!-- Gli ingressi verranno inseriti qui dinamicamente -->
          </ul>
        </div>
      </div>
    </nav>
  </header>

  <main>
    <div class="location-info">
      <!-- Box con le informazioni di contatto -->
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
      <!-- Contenitore per la mappa -->
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


  <!-- Script per la gestione del popup di logout -->
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
        window.location.href = "index.php?logout=true";
    }
  </script>

  <!-- Script per AJAX e inizializzazione della mappa -->
  <script>
    // Carica gli ingressi dal database
    function caricaIngressi() {
      $.ajax({
        url: "carica_ingressi.php",
        type: "GET",
        success: function(data) {
          $("#lista-ingressi").html(data);
        }
      });
    }
    $(document).ready(function() {
      caricaIngressi();
      // Gestione click sul menu hamburger
      $("#menu-toggle").click(function() {
        $("#side-menu").toggleClass("show");
      });
    });
    // Inizializzazione della mappa Google
    function initMap() {
      var location = { lat: 41.58968431671539, lng: 12.635202139684328 };
      var map = new google.maps.Map(document.getElementById("map"), {
        zoom: 15,
        center: location,
      });
      new google.maps.Marker({
        position: location,
        map: map,
        title: "Body Fit Palestra"
      });
    }
  </script>

  <footer>
    <p>&copy; 2025 Body Fit Palestra | Sviluppato da <strong>Riccardo</strong></p>
  </footer>
</body>
</html>
