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
if ($result->num_rows == 0) {
    $utente = null;
    $abbonamento = null;
} else {
    $utente = $result->fetch_assoc();

    // Recuperiamo anche i dati dell'abbonamento dall'entità 'iscrizione'
    $codice_fiscale = $utente['codice_fiscale'];
    $sql_abbonamento = "SELECT * FROM iscrizione WHERE cod_iscritto = ?";
    $stmt_abbonamento = $conn->prepare($sql_abbonamento);
    $stmt_abbonamento->bind_param("s", $codice_fiscale);
    $stmt_abbonamento->execute();
    $result_abbonamento = $stmt_abbonamento->get_result();

    $abbonamento = null;
    $data_iscrizione = $data_fine = $prezzo_finale = $tipo_abbonamento = '';

    if ($result_abbonamento->num_rows > 0) {
        $abbonamento = $result_abbonamento->fetch_assoc();
        $data_iscrizione = $abbonamento['data_iscrizione'];
        $data_fine = $abbonamento['data_fine'];
        $prezzo_finale = $abbonamento['prezzo_finale'];
        $cod_pacchetto = $abbonamento['cod_pacchetto'];

        // Formattiamo la data per visualizzare solo giorno, mese e anno
        $data_iscrizione = date("d-m-Y", strtotime($data_iscrizione));
        $data_fine = date("d-m-Y", strtotime($data_fine));

        // Determiniamo il tipo di abbonamento in base al codice pacchetto
        switch ($cod_pacchetto) {
            case 1:
                $tipo_abbonamento = "Corsi";
                break;
            case 2:
                $tipo_abbonamento = "Sala Pesi";
                break;
            case 3:
                $tipo_abbonamento = "Corsi + Sala Pesi";
                break;
            default:
                $tipo_abbonamento = "Sconosciuto";
        }
    }
}

// Gestione del logout
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profilo Utente</title>
  <!-- Collega il file CSS esterno -->
  <link rel="stylesheet" href="style/styles_profilo_cliente.css">
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
  <h1>Profilo Utente</h1>
  <?php if ($utente): ?>
    <div class="profile-box">
      <p><strong>Nome:</strong> <?php echo $utente['nome']; ?></p>
      <p><strong>Cognome:</strong> <?php echo $utente['cognome']; ?></p>
      <p><strong>Codice Fiscale:</strong> <?php echo $utente['codice_fiscale']; ?></p>
      <p><strong>Email:</strong> <?php echo $utente['email']; ?></p>
      <p><strong>Telefono:</strong> <?php echo $utente['telefono']; ?></p>
      <p><strong>Data di Nascita:</strong> <?php echo $utente['data_nascita']; ?></p>
      <p><strong>Indirizzo:</strong> <?php echo $utente['indirizzo']; ?></p>
    </div>
  <?php else: ?>
    <div class="profile-box">
      <p>Non hai un profilo iscritto alla palestra. Recati presso la nostra sede per fare l'iscrizione! 💪✅</p>
    </div>
  <?php endif; ?>

  <?php if ($abbonamento): ?>
    <div class="profile-box">
      <h2>Stato Abbonamento</h2>
      <p><strong>Data Iscrizione:</strong> <?php echo $data_iscrizione; ?></p>
      <p><strong>Data Fine:</strong> <?php echo $data_fine; ?></p>
      <p><strong>Prezzo Finale:</strong> €<?php echo $prezzo_finale; ?></p>
      <p><strong>Tipo Abbonamento:</strong> <?php echo $tipo_abbonamento; ?></p>
    </div>
  <?php else: ?>
    <div class="profile-box">
      <p>Non hai ancora un abbonamento attivo. Scegli il tuo abbonamento dalla nostra offerta!</p>
    </div>
  <?php endif; ?>

  <!-- Pulsante per cambiare la password -->
  <div class="profile-box" style="text-align:center;">
      <button onclick="window.location.href='change_password.php';" class="btn-change-password">Cambia Password</button>
  </div>
</main>

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
    window.location.href = "profilo_cliente.php?logout=true";
}
</script>

<footer>
  <p>&copy; 2025 Body Fit Palestra | Sviluppato da <strong>Riccardo</strong></p>
</footer>
</body>
</html>
