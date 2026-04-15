<?php
// Includi la connessione al database
include('db.php');

// Avvia la sessione
session_start();

// Gestione logout: se il parametro logout=true è presente, distruggi la sessione e reindirizza al login.
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Verifica se l'utente è loggato
if (!isset($_SESSION['email'])) {
    header('Location: login_dipendente.php'); // Redirigi alla pagina di login se non è loggato
    exit;
}

// Recupera l'email dell'utente loggato dalla sessione
$email = $_SESSION['email']; 

// Prepara la query per trovare il dipendente con la stessa email nella tabella 'dipendente'
$sql = "SELECT * FROM dipendente WHERE email = ?";
$stmt = $conn->prepare($sql);

// Verifica se la query è stata preparata correttamente
if (!$stmt) {
    die("Errore nella preparazione della query: " . $conn->error);
}

// Lega il parametro (email) ed esegui la query
$stmt->bind_param("s", $email); // Usa l'email del dipendente loggato
$stmt->execute();
$result = $stmt->get_result();

// Verifica se il dipendente è stato trovato
if ($result->num_rows == 0) {
    $dipendente = null;
} else {
    $dipendente = $result->fetch_assoc();
}

// Se il dipendente è stato trovato, recupera informazioni aggiuntive in base alla tipologia
$extra_info = null;
if ($dipendente) {
    // Utilizziamo il campo 'codice_fiscale' come identificativo
    $identificativo = $dipendente['codice_fiscale'];
    $tipologia = $dipendente['tipologia_dipendenti']; // 1: istruttore, 2: insegnante, 3: receptionist

    // Seleziona la query in base alla tipologia del dipendente
    switch ($tipologia) {
        case 1: // Istruttore
            $sql_tipo = "SELECT specializzazione_istr, ore_lavorative_contrattuali, stipendio_istr FROM istruttore WHERE cod_dipendente = ?";
            break;
        case 2: // Insegnante
            $sql_tipo = "SELECT specializzazione_ins, ore_lavorative_contrattuali, stipendio_ins FROM insegnante WHERE cod_dipendente = ?";
            break;
        case 3: // Receptionist
            $sql_tipo = "SELECT specializzazione_rec, ore_lavorative_contrattuali, stipendio_rec FROM receptionist WHERE cod_dipendente = ?";
            break;
        default:
            $sql_tipo = "";
    }
    
    // Esegui la query se esiste una query per la tipologia
    if ($sql_tipo != "") {
        $stmt2 = $conn->prepare($sql_tipo);
        if (!$stmt2) {
            die("Errore nella preparazione della query per la tipologia: " . $conn->error);
        }
        // Bindiamo il parametro identificativo (codice_fiscale) come stringa ("s")
        $stmt2->bind_param("s", $identificativo);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        if ($result2->num_rows > 0) {
            $extra_info = $result2->fetch_assoc();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilo Dipendente</title>
    <link rel="stylesheet" href="style/styles_profilo_dipendente.css">
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
            <li><a href="home_dipendente.php">Home</a></li>
            <li><a href="promo_dipendente.php">Prezzi</a></li>
            <li><a href="calendario_dipendente.php">Calendario</a></li>
            <li><a href="location_dipendente.php">Dove siamo</a></li>
            <li><a href="profilo_dipendente.php">Profilo</a></li>
            <li><a href="#" onclick="openLogoutPopup();">Logout</a></li>
        </ul>
        <hr class="title-underline">
    </nav>
</header>

<main>
    <h1>Profilo Dipendente</h1>

    <?php if ($dipendente): ?>
        <!-- Box principale del profilo dipendente -->
        <div class="profile-box">
            <p><strong>Nome:</strong> <?php echo $dipendente['nome']; ?></p>
            <p><strong>Cognome:</strong> <?php echo $dipendente['cognome']; ?></p>
            <p><strong>Codice Fiscale:</strong> <?php echo $dipendente['codice_fiscale']; ?></p>
            <p><strong>Email:</strong> <?php echo $dipendente['email']; ?></p>
            <p><strong>Telefono:</strong> <?php echo $dipendente['telefono']; ?></p>
            <p><strong>Data di Nascita:</strong> <?php echo $dipendente['data_nascita']; ?></p>
            <p><strong>Data di Assunzione:</strong> <?php echo $dipendente['data_assunzione']; ?></p>
        </div>

        <!-- Box aggiuntivo con informazioni specifiche in base alla tipologia -->
        <?php if ($extra_info): ?>
            <div class="profile-box">
                <h2>Dettagli Professionali</h2>
                <?php if ($dipendente['tipologia_dipendenti'] == 1): ?>
                    <p><strong>Specializzazione Istruttore:</strong> <?php echo $extra_info['specializzazione_istr']; ?></p>
                    <p><strong>Ore Lavorative Contrattuali:</strong> <?php echo $extra_info['ore_lavorative_contrattuali']; ?></p>
                    <p><strong>Stipendio Istruttore:</strong> <?php echo $extra_info['stipendio_istr']; ?></p>
                <?php elseif ($dipendente['tipologia_dipendenti'] == 2): ?>
                    <p><strong>Specializzazione Insegnante:</strong> <?php echo $extra_info['specializzazione_ins']; ?></p>
                    <p><strong>Ore Lavorative Contrattuali:</strong> <?php echo $extra_info['ore_lavorative_contrattuali']; ?></p>
                    <p><strong>Stipendio Insegnante:</strong> <?php echo $extra_info['stipendio_ins']; ?></p>
                <?php elseif ($dipendente['tipologia_dipendenti'] == 3): ?>
                    <p><strong>Specializzazione Receptionist:</strong> <?php echo $extra_info['specializzazione_rec']; ?></p>
                    <p><strong>Ore Lavorative Contrattuali:</strong> <?php echo $extra_info['ore_lavorative_contrattuali']; ?></p>
                    <p><strong>Stipendio Receptionist:</strong> <?php echo $extra_info['stipendio_rec']; ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Se il dipendente non è trovato -->
        <div class="profile-box">
            <p>Non hai un profilo associato. Contatta l'amministratore per risolvere il problema!</p>
        </div>
    <?php endif; ?>
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
        window.location.href = "profilo_dipendente.php?logout=true"; // Aggiunge il parametro di logout alla URL
    }
</script>

<!-- Footer -->
<footer>
    <p>&copy; 2025 Body Fit Palestra | Sviluppato da <strong>Riccardo</strong></p>
</footer>

</body>
</html>
