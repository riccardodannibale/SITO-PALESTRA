<?php
session_start();
include('db.php');

// Verifica che l'utente sia loggato
if (!isset($_SESSION['email'])) {
    header("Location: login_dipendente.php");
    exit();
}

$email = $_SESSION['email'];

// Recupera il codice fiscale e il tipo di dipendente dal record in "dipendente"
// Il dipendente è receptionist se tipologia_dipendenti == 3
$stmt = $conn->prepare("SELECT d.codice_fiscale, d.tipologia_dipendenti 
                        FROM utenti u 
                        JOIN dipendente d ON u.email = d.email 
                        WHERE u.email = ? AND u.is_dipendente = 1");
if (!$stmt) {
    echo "Errore nella preparazione della query: " . $conn->error;
    exit();
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    echo "Dipendente non trovato o non autorizzato.";
    exit();
}
$cod_receptionist = $user['codice_fiscale'];
$is_receptionist = ($user['tipologia_dipendenti'] == 3); // true se receptionist
$stmt->close();

// ====================================================
// 1. Endpoint per caricare gli iscritti con abbonamento attivo (via AJAX)
// ====================================================
if (isset($_GET['action']) && $_GET['action'] == 'carica_iscritti') {
    $query = "SELECT DISTINCT i.codice_fiscale, i.nome, i.cognome, i.data_nascita, i.email, i.telefono, i.indirizzo 
              FROM iscritto i 
              JOIN iscrizione s ON i.codice_fiscale = s.cod_iscritto 
              WHERE CURDATE() <= s.data_fine 
              ORDER BY i.nome";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "<li>
                    <strong>" . htmlspecialchars($row['nome']) . " " . htmlspecialchars($row['cognome']) . "</strong><br>
                    Codice Fiscale: " . htmlspecialchars($row['codice_fiscale']) . "<br>
                    Data Nascita: " . htmlspecialchars($row['data_nascita']) . "<br>
                    Email: " . htmlspecialchars($row['email']) . "<br>
                    Telefono: " . htmlspecialchars($row['telefono']) . "<br>
                    Indirizzo: " . htmlspecialchars($row['indirizzo']) . "
                  </li>";
        }
    } else {
        echo "<li>Errore nel caricamento degli iscritti.</li>";
    }
    exit();
}

// ====================================================
// 2. Endpoint per salvare un nuovo iscritto (richiamato via AJAX dal form)
// ====================================================
if (isset($_POST['saveNewIscritto'])) {

    // Verifica che l'utente sia un receptionist (tipologia_dipendenti == 3)
    if (!$is_receptionist) {
        echo "Non sei autorizzato ad inserire un nuovo iscritto.";
        exit();
    }

    // Recupera e "pulisce" i dati per la tabella "iscritto"
    $codice_fiscale = trim($_POST['codice_fiscale']);
    $nome           = trim($_POST['nome']);
    $cognome        = trim($_POST['cognome']);
    $data_nascita   = $_POST['data_nascita'];
    $emailIscritto  = trim($_POST['email']);  // email dell'iscritto
    $telefono       = trim($_POST['telefono']);
    $indirizzo      = trim($_POST['indirizzo']);

    // Recupera i dati per la tabella "iscrizione"
    $cod_pacchetto   = isset($_POST['cod_pacchetto']) ? (int)$_POST['cod_pacchetto'] : 0;
    if ($cod_pacchetto <= 0) {
        echo "Seleziona un pacchetto valido.";
        exit();
    }
    $data_iscrizione = $_POST['data_iscrizione'];
    $data_fine       = $_POST['data_fine'];
    $prezzo_finale   = isset($_POST['prezzo_finale']) ? floatval($_POST['prezzo_finale']) : 0.0;

    // Avvia una transazione per assicurare l'inserimento in entrambe le tabelle
    $conn->begin_transaction();

    // Inserimento nella tabella "iscritto"
    $stmtIscritto = $conn->prepare("INSERT INTO iscritto (codice_fiscale, nome, cognome, data_nascita, email, telefono, indirizzo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmtIscritto) {
        echo "Errore nella preparazione della query per iscritto: " . $conn->error;
        exit();
    }
    $stmtIscritto->bind_param("sssssss", $codice_fiscale, $nome, $cognome, $data_nascita, $emailIscritto, $telefono, $indirizzo);
    if (!$stmtIscritto->execute()) {
        $conn->rollback();
        echo "Errore nell'inserimento dell'iscritto: " . $stmtIscritto->error;
        exit();
    }
    $stmtIscritto->close();

    // Verifica che il record sia stato inserito correttamente
    $check = $conn->prepare("SELECT codice_fiscale FROM iscritto WHERE codice_fiscale = ?");
    $check->bind_param("s", $codice_fiscale);
    $check->execute();
    $resultCheck = $check->get_result();
    if ($resultCheck->num_rows == 0) {
        $conn->rollback();
        echo "Inserimento dell'iscritto non riuscito.";
        exit();
    }
    $check->close();

    // Inserimento nella tabella "iscrizione"
    $stmtIscrizione = $conn->prepare("INSERT INTO iscrizione (cod_pacchetto, cod_receptionist, cod_iscritto, data_iscrizione, data_fine, prezzo_finale) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmtIscrizione) {
        $conn->rollback();
        echo "Errore nella preparazione della query per iscrizione: " . $conn->error;
        exit();
    }
    $stmtIscrizione->bind_param("issssd", $cod_pacchetto, $cod_receptionist, $codice_fiscale, $data_iscrizione, $data_fine, $prezzo_finale);
    if (!$stmtIscrizione->execute()) {
        $conn->rollback();
        echo "Errore nell'inserimento dell'iscrizione: " . $stmtIscrizione->error;
        exit();
    }
    $stmtIscrizione->close();

    $conn->commit();
    echo "Iscritto inserito con successo!";
    exit();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fitness Studio - Dipendente</title>
  <!-- Collega il file CSS esterno -->
  <link rel="stylesheet" href="style/styles_home_dipendente.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
  <!-- Div per il messaggio di successo per "Nuovo Iscritto" -->
  <div id="custom-alert" class="custom-alert hidden">
    Nuovo iscritto aggiunto! Benvenuto nella nostra famiglia Body Fit!
  </div>
  <!-- Div per il messaggio di successo per l'invio dei messaggi -->
  <div id="custom-alert-message" class="custom-alert hidden"></div>
  
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
        <span class="menu-text">Controllo iscritti attivi palestra</span>
      </div>

      <hr class="title-underline">
      <ul>
        <li><a href="home_dipendente.php">Home</a></li>
        <li><a href="promo_dipendente.php">Prezzi</a></li>
        <li><a href="calendario_dipendente.php">Calendario</a></li>
        <li><a href="location_dipendente.php">Dove siamo</a></li>
        <li><a href="profilo_dipendente.php">Profilo</a></li>
        <li><a href="#" onclick="openLogoutPopup();">Logout</a></li>
      </ul>
      <hr class="title-underline">
      <!-- Menu a scomparsa -->
      <div id="side-menu">
        <div class="menu-content">
          <h3>Iscritti attivi</h3>
          <!-- Bottone per aggiungere un nuovo iscritto -->
          <button id="newIscrittoButton" class="btn" 
            <?php if(!$is_receptionist) echo 'disabled style="opacity: 0.5; cursor: not-allowed;"'; ?>>
            + Aggiungi iscritto
          </button>
          <!-- Lista degli iscritti (caricati via AJAX) -->
          <ul id="lista-iscritti"></ul>
        </div>
      </div>
    </nav>
  </header>

  <!-- Box Messaggi -->
  <div class="message-box">
    <h3>Messaggi dalla Palestra</h3>
    <div id="messaggi-container">
      <!-- I messaggi vengono caricati qui -->
    </div>
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

  <!-- Popup per inserire un nuovo iscritto -->
  <div id="new-iscritto-popup" class="popup-overlay">
    <div class="popup-box">
      <h2>Inserisci nuovo iscritto</h2>
      <form id="newIscrittoForm">
        <!-- Campo nascosto per indicare l'azione -->
        <input type="hidden" name="saveNewIscritto" value="1">
        <!-- Campi per la tabella 'iscritto' -->
        <input type="text" name="codice_fiscale" placeholder="Codice Fiscale" required>
        <input type="text" name="nome" placeholder="Nome" required>
        <input type="text" name="cognome" placeholder="Cognome" required>
        <input type="date" name="data_nascita" placeholder="Data di Nascita" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="text" name="telefono" placeholder="Telefono" required>
        <input type="text" name="indirizzo" placeholder="Indirizzo" required>
        
        <!-- Campi per la tabella 'iscrizione' -->
        <select name="cod_pacchetto" required>
          <option value="">Seleziona Pacchetto</option>
          <option value="1">Corsi</option>
          <option value="2">Sala Pesi</option>
          <option value="3">Mix</option>
        </select>
        <input type="date" name="data_iscrizione" id="data_iscrizione" required>
        <input type="date" name="data_fine" id="data_fine" readonly placeholder="Data Fine (1 anno dopo)">
        <input type="number" name="prezzo_finale" placeholder="Prezzo Finale" step="0.01" required>
        
        <div class="popup-buttons">
          <button type="submit" class="btn confirm">Salva</button>
          <button type="button" class="btn cancel" onclick="closeNewIscrittoPopup();">Annulla</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Form per l'invio dei messaggi -->
  <div class="message-form">
    <h3>Invia un messaggio</h3>
    <form id="messageForm">
      <textarea name="contenuto" id="contenuto" rows="3" placeholder="Scrivi un messaggio..." required></textarea>
      <button type="submit">Invia</button>
    </form>
  </div>

  <script>
    // Funzioni per il popup di logout
    function openLogoutPopup() {
      document.getElementById("logout-popup").style.display = "flex";
      document.body.style.overflow = "hidden";
    }
    function closeLogoutPopup() {
      document.getElementById("logout-popup").style.display = "none";
      document.body.style.overflow = "auto";
    }
    function confirmLogout() {
      window.location.href = "index.php";
    }
    
    // Funzioni per il popup "Nuovo Iscritto"
    function openNewIscrittoPopup() {
      document.getElementById("new-iscritto-popup").style.display = "flex";
      document.body.style.overflow = "hidden";
    }
    function closeNewIscrittoPopup() {
      document.getElementById("new-iscritto-popup").style.display = "none";
      document.body.style.overflow = "auto";
    }
    
    // Carica gli iscritti tramite AJAX (aggiungendo un parametro timestamp per evitare cache)
    function caricaIscritti() {
      $.ajax({
        url: "?action=carica_iscritti&_=" + new Date().getTime(),
        type: "GET",
        success: function(data) {
          $("#lista-iscritti").html(data);
        },
        error: function() {
          $("#lista-iscritti").html("<li>Errore nel caricamento degli iscritti.</li>");
        }
      });
    }
    
    // Carica i messaggi (presupponendo l'esistenza di carica_messaggi.php)
    function caricaMessaggi() {
      $.ajax({
        url: "carica_messaggi.php",
        type: "GET",
        success: function(data) {
          $("#messaggi-container").html(data);
        },
        error: function() {
          $("#messaggi-container").html("<p>Errore nel caricamento dei messaggi.</p>");
        }
      });
    }
    
    $(document).ready(function() {
      // Carica iscritti e messaggi all'avvio
      caricaIscritti();
      caricaMessaggi();
      setInterval(caricaMessaggi, 5000);

      // Menu hamburger
      $("#menu-toggle").click(function() {
        $("#side-menu").toggleClass("show");
      });

      // Apertura del popup "Nuovo Iscritto"
      $("#newIscrittoButton").click(function() {
        openNewIscrittoPopup();
      });
      
      // Calcola automaticamente la data di fine iscrizione (1 anno dopo)
      $("#data_iscrizione").change(function() {
        var dataIn = new Date($(this).val());
        if (!isNaN(dataIn)) {
          var dataOut = new Date(dataIn);
          dataOut.setFullYear(dataOut.getFullYear() + 1);
          var mese = ("0" + (dataOut.getMonth() + 1)).slice(-2);
          var giorno = ("0" + dataOut.getDate()).slice(-2);
          var formattedDate = dataOut.getFullYear() + "-" + mese + "-" + giorno;
          $("#data_fine").val(formattedDate);
        }
      });

      // Gestione submit del form "Nuovo Iscritto" con messaggio di alert personalizzato
      $("#newIscrittoForm").submit(function(e) {
        e.preventDefault();
        $.ajax({
          url: "", // Il form viene inviato a questo stesso file
          type: "POST",
          data: $(this).serialize(),
          success: function(response) {
            if(response.trim() === "Iscritto inserito con successo!") {
              // Mostra il messaggio custom invece del classico alert()
              $("#custom-alert").removeClass("hidden").addClass("show");
              // Nasconde il messaggio dopo 3 secondi
              setTimeout(function() {
                $("#custom-alert").removeClass("show").addClass("hidden");
              }, 3000);
            } else {
              alert(response);
            }
            // Ricarica la lista degli iscritti
            caricaIscritti();
            closeNewIscrittoPopup();
            $("#newIscrittoForm")[0].reset();
          },
          error: function() {
            alert("Errore durante l'inserimento dell'iscritto.");
          }
        });
      });

      // Gestione submit del form "Invia Messaggio" con messaggio di alert personalizzato
      $("#messageForm").submit(function(e) {
        e.preventDefault();
        $.ajax({
          url: "invia_messaggio.php",
          type: "POST",
          data: $(this).serialize(),
          success: function(response) {
            // Se il messaggio è stato inviato correttamente mostriamo il custom alert
            if(response.trim() === "Messaggio inviato con successo!") {
              $("#custom-alert-message").text("Messaggio inviato con successo!").removeClass("hidden").addClass("show");
              setTimeout(function() {
                $("#custom-alert-message").removeClass("show").addClass("hidden");
              }, 3000);
            } else {
              // In caso di errore, puoi scegliere di utilizzare un alert tradizionale oppure mostrare un messaggio simile
              alert(response);
            }
            $("#contenuto").val("");
            caricaMessaggi();
          },
          error: function() {
            alert("Errore durante l'invio del messaggio.");
          }
        });
      });
    });
  </script>

  <footer>
    <p>&copy; 2025 Body Fit Palestra | Sviluppato da <strong>Riccardo</strong></p>
  </footer>

</body>
</html>
