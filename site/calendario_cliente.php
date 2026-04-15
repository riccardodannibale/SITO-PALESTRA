<?php
// Abilita la segnalazione degli errori per il debug (disabilita in produzione)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connessione al database
include('db.php');

// Avvia la sessione
session_start();

// Verifica se l'utente è loggato
if (!isset($_SESSION['email'])) {
    header("Location: login_cliente.php");
    exit;
}

// Recupera l'email dell'utente loggato
$email = $_SESSION['email'];

// Query per ottenere il codice fiscale dell'utente loggato
$queryCodFiscale = "SELECT codice_fiscale FROM iscritto WHERE email = ?";
$stmtCodFiscale = $conn->prepare($queryCodFiscale);
$stmtCodFiscale->bind_param("s", $email);
$stmtCodFiscale->execute();
$resultCodFiscale = $stmtCodFiscale->get_result();

if ($resultCodFiscale->num_rows > 0) {
    $utente = $resultCodFiscale->fetch_assoc();
    $codIscritto = $utente['codice_fiscale'];
} else {
    die("Utente non trovato.");
}

// Query per verificare l'abbonamento attivo
$queryAbbonamento = "SELECT data_fine, cod_pacchetto FROM iscrizione WHERE cod_iscritto = ? ORDER BY data_iscrizione DESC LIMIT 1";
$stmtAbbonamento = $conn->prepare($queryAbbonamento);
$stmtAbbonamento->bind_param("s", $codIscritto);
$stmtAbbonamento->execute();
$resultAbbonamento = $stmtAbbonamento->get_result();

$abbonamentoAttivo = false;
if ($resultAbbonamento->num_rows > 0) {
    $abbonamento = $resultAbbonamento->fetch_assoc();
    $dataFineAbbonamento = $abbonamento['data_fine'];
    $codPacchetto = $abbonamento['cod_pacchetto'];
    if (strtotime($dataFineAbbonamento) >= time() && ($codPacchetto == 1 || $codPacchetto == 3)) {
        $abbonamentoAttivo = true;
    }
}

// Query per ottenere i corsi disponibili (menu extra)
$queryCorsi = "SELECT id_corso, nome, livello_difficolta FROM corso";
$stmtCorsi = $conn->prepare($queryCorsi);
$stmtCorsi->execute();
$resultCorsi = $stmtCorsi->get_result();

$message = "";
$messageSuccess = "";

// Gestione della prenotazione della lezione
if (isset($_POST['prenota'])) {
    if (!$abbonamentoAttivo) {
        $message = "Non hai un abbonamento attivo o il tuo abbonamento non consente la prenotazione delle lezioni! Acquistalo per prenotare le lezioni.";
    } else {
        $data = $_POST['data'];
        $orario = $_POST['orario'];
        $corso = $_POST['corso']; // ID del corso selezionato
        // Uniamo data e orario per ottenere il timestamp completo della prenotazione
        $dataPrenotazione = $data . ' ' . $orario . ':00';

        // Controlla che l'utente non abbia già prenotato questa lezione
        $checkQuery = "SELECT * FROM prenotazione WHERE cod_iscritto = ? AND cod_corso = ? AND data_prenotazione = ?";
        $stmtCheck = $conn->prepare($checkQuery);
        $stmtCheck->bind_param("sss", $codIscritto, $corso, $dataPrenotazione);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        if ($resultCheck->num_rows > 0) {
            $message = "Hai già prenotato questa lezione.";
        } else {
            // Inserisce la prenotazione e imposta cod_rec a NULL
            $queryPrenota = "INSERT INTO prenotazione (cod_iscritto, cod_corso, data_prenotazione, cod_rec) VALUES (?, ?, ?, NULL)";
            $stmtPrenota = $conn->prepare($queryPrenota);
            $stmtPrenota->bind_param("sss", $codIscritto, $corso, $dataPrenotazione);

            if ($stmtPrenota->execute()) {
                $messageSuccess = "Hai prenotato con successo la lezione!";
            } else {
                $message = "Errore nella prenotazione. Riprova.";
            }
        }
    }
} elseif (isset($_POST['elimina'])) {
    // Gestione dell'eliminazione della prenotazione
    $data = $_POST['data'];
    $orario = $_POST['orario'];
    $corso = $_POST['corso'];
    $dataPrenotazione = $data . ' ' . $orario . ':00';

    $queryElimina = "DELETE FROM prenotazione WHERE cod_iscritto = ? AND cod_corso = ? AND data_prenotazione = ?";
    $stmtElimina = $conn->prepare($queryElimina);
    $stmtElimina->bind_param("sss", $codIscritto, $corso, $dataPrenotazione);

    if ($stmtElimina->execute()) {
        $messageSuccess = "Prenotazione eliminata con successo.";
    } else {
        $message = "Errore nell'eliminazione della prenotazione. Riprova.";
    }
}

// Gestione della navigazione settimanale nel calendario
$weekOffset = isset($_GET['week_offset']) ? (int)$_GET['week_offset'] : 0;
$mondayThisWeek = strtotime("monday this week");
$startOfWeek = date('Y-m-d', strtotime("+$weekOffset week", $mondayThisWeek));
$endOfWeek   = date('Y-m-d 23:59:59', strtotime("+6 days +$weekOffset week", $mondayThisWeek));

// Query per ottenere le lezioni della settimana corrente
$query = "
    SELECT 
        l.data_lezione, 
        c.nome AS corso_nome, 
        c.id_corso,
        c.max_partecipanti,
        CONCAT(d.nome, ' ', d.cognome) AS insegnante_nome,
        COUNT(p.cod_iscritto) AS prenotazioni
    FROM lezione l
    INNER JOIN corso c ON l.cod_corso = c.id_corso
    INNER JOIN insegnante i ON l.cod_insegnante = i.cod_dipendente
    INNER JOIN dipendente d ON i.cod_dipendente = d.codice_fiscale
    LEFT JOIN prenotazione p ON p.cod_corso = c.id_corso AND DATE(p.data_prenotazione) = DATE(l.data_lezione)
    WHERE l.data_lezione BETWEEN ? AND ?
    GROUP BY l.data_lezione, c.id_corso, corso_nome, insegnante_nome, c.max_partecipanti
    ORDER BY l.data_lezione;
";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $startOfWeek, $endOfWeek);
$stmt->execute();
$result = $stmt->get_result();

$lessons = [];
while ($row = $result->fetch_assoc()) {
    // Calcola l'indice del giorno (0 per lunedì, 6 per domenica)
    $dayIndex = date('N', strtotime($row['data_lezione'])) - 1;
    $time = date('H:i', strtotime($row['data_lezione']));
    $lessons[$dayIndex][$time] = [
        'corso_nome'      => $row['corso_nome'],
        'insegnante_nome' => $row['insegnante_nome'],
        'id_corso'        => $row['id_corso'],
        'max_partecipanti'=> $row['max_partecipanti'],
        'prenotazioni'    => $row['prenotazioni']
    ];
}

// Recupera le prenotazioni dell'iscritto nella settimana corrente
$userBookings = [];
$queryUserBookings = "SELECT data_prenotazione, cod_corso FROM prenotazione WHERE cod_iscritto = ? AND DATE(data_prenotazione) BETWEEN ? AND ?";
$stmtUserBookings = $conn->prepare($queryUserBookings);
$stmtUserBookings->bind_param("sss", $codIscritto, $startOfWeek, $endOfWeek);
$stmtUserBookings->execute();
$resultUserBookings = $stmtUserBookings->get_result();
while ($row = $resultUserBookings->fetch_assoc()){
    $date = date('Y-m-d', strtotime($row['data_prenotazione']));
    $time = date('H:i', strtotime($row['data_prenotazione']));
    $corso = $row['cod_corso'];
    $key = $date . '|' . $time . '|' . $corso;
    $userBookings[$key] = true;
}

// Query per il resoconto di tutte le prenotazioni dell'iscritto
$querySummary = "SELECT DISTINCT p.data_prenotazione, c.nome AS corso_nome, c.livello_difficolta 
                 FROM prenotazione p 
                 INNER JOIN corso c ON p.cod_corso = c.id_corso 
                 WHERE p.cod_iscritto = ? 
                 ORDER BY p.data_prenotazione";
$stmtSummary = $conn->prepare($querySummary);
$stmtSummary->bind_param("s", $codIscritto);
$stmtSummary->execute();
$resultSummary = $stmtSummary->get_result();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario Lezioni</title>
    <link rel="stylesheet" href="style/styles_calendario_cliente.css">
</head>
<body>
    <header>
        <nav>
            <!-- Logo e menu statico -->
            <div class="logo">
                <img src="style/img/1.png" alt="Left Image" class="logo-image left">
                <span>BODY <span>FIT</span></span>
                <img src="style/img/2.png" alt="Right Image" class="logo-image right">
            </div>
            <hr class="title-underline">
            <!-- Bottone hamburger e titolo per il menu extra -->
            <div class="hamburger-wrapper">
                <div class="hamburger-btn" onclick="toggleExtraMenu()">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <span class="hamburger-title">Corsi disponibili</span>
            </div>
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
        <!-- Menu extra (dropdown) per visualizzare i corsi -->
        <div class="extra-menu" id="extra-menu">
            <?php 
            if ($resultCorsi->num_rows > 0) {
                while ($corso = $resultCorsi->fetch_assoc()) {
                    $nomeCorso = htmlspecialchars($corso['nome']);
                    $livello = htmlspecialchars($corso['livello_difficolta']);
                    echo "<div class='menu-box'>";
                    echo "<h3>$nomeCorso</h3>";
                    echo "<p>Difficoltà: $livello</p>";
                    echo "</div>";
                }
            } else {
                echo "<div class='menu-box'><p>Nessun corso disponibile</p></div>";
            }
            ?>
        </div>
    </header>

    <!-- Popup di Logout -->
    <div id="logout-popup" class="popup-overlay">
        <div class="popup-box">
            <h2>Sei sicuro di voler effettuare il logout?</h2>
            <div class="popup-buttons">
                <button class="btn confirm" onclick="confirmLogout()">Sì, esci</button>
                <button class="btn cancel" onclick="closeLogoutPopup()">Annulla</button>
            </div>
        </div>
    </div>

    <!-- Messaggi di errore o conferma -->
    <?php if ($message): ?>
        <div class="alert-box show" id="alert-box">
            <?php echo $message; ?>
            <button onclick="closeAlert()">OK</button>
        </div>
    <?php endif; ?>
    <?php if ($messageSuccess): ?>
        <div class="success-box show" id="success-box">
            <?php echo $messageSuccess; ?>
            <button onclick="closeSuccess()">OK</button>
        </div>
    <?php endif; ?>

    <!-- Calendario Lezioni -->
    <div class="calendar">
        <h2>Calendario Lezioni</h2>
        <!-- Navigazione settimanale -->
        <div class="calendar-navigation">
            <?php 
            if ($weekOffset > 0) {
                $prevOffset = $weekOffset - 1;
                echo '<a href="?week_offset=' . $prevOffset . '">Settimana Precedente</a>';
            }
            if ($weekOffset != 0) {
                echo '<a href="?week_offset=0">Settimana Corrente</a>';
            }
            $nextOffset = $weekOffset + 1;
            echo '<a href="?week_offset=' . $nextOffset . '">Settimana Successiva</a>';
            ?>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Orario</th>
                    <?php
                    $giorni = ["Lunedì", "Martedì", "Mercoledì", "Giovedì", "Venerdì", "Sabato", "Domenica"];
                    for ($i = 0; $i < 7; $i++) {
                        $cellDate = date('Y-m-d', strtotime("$startOfWeek + $i days"));
                        $displayDate = date('d/m', strtotime($cellDate));
                        $highlightClass = ($weekOffset == 0 && (date('Y-m-d') == $cellDate)) ? 'highlight-column' : '';
                        echo "<th class='$highlightClass'>{$giorni[$i]}<br>($displayDate)</th>";
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $hours = ["08:00", "09:00", "10:00", "11:00", "12:00", "13:00", "14:00", "15:00", "16:00", "17:00", "18:00", "19:00"];
                foreach ($hours as $hour) {
                    echo "<tr><td class='time-column'>$hour</td>";
                    for ($i = 0; $i < 7; $i++) {
                        echo "<td class='lesson-cell'>";
                        if (isset($lessons[$i][$hour])) {
                            $lesson = $lessons[$i][$hour];
                            echo htmlspecialchars($lesson['corso_nome']) . " - " . htmlspecialchars($lesson['insegnante_nome']);
                            echo "<div class='booking-info'>" 
                                . $lesson['prenotazioni'] . "/" . $lesson['max_partecipanti']
                                . "</div>";
                            $cellDate = date('Y-m-d', strtotime("$startOfWeek + $i days"));
                            $bookingKey = $cellDate . '|' . $hour . '|' . $lesson['id_corso'];
                            echo "<form method='POST' style='display:inline;'>";
                            echo "<input type='hidden' name='data' value='$cellDate'>";
                            echo "<input type='hidden' name='orario' value='$hour'>";
                            echo "<input type='hidden' name='corso' value='" . htmlspecialchars($lesson['id_corso']) . "'>";
                            
                            if (isset($userBookings[$bookingKey])) {
                                echo "<button type='submit' name='elimina' class='book-button elimina'>Elimina Prenotazione</button>";
                            } else {
                                $lessonTimestamp = strtotime("$cellDate $hour");
                                if ($abbonamentoAttivo && $lessonTimestamp >= time()) {
                                    if ($lesson['prenotazioni'] >= $lesson['max_partecipanti']) {
                                        echo "<button type='button' class='book-button disabled' disabled>Prenota</button>";
                                    } else {
                                        echo "<button type='submit' name='prenota' class='book-button'>Prenota</button>";
                                    }
                                } else {
                                    echo "<button type='button' class='book-button disabled' disabled>Prenota</button>";
                                }
                            }
                            echo "</form>";
                        }
                        echo "</td>";
                    }
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Resoconto Prenotazioni -->
    <div class="booking-summary">
        <h2>Resoconto Prenotazioni</h2>
        <?php
        if ($resultSummary->num_rows > 0) {
            echo "<table class='summary-table'>";
            echo "<thead><tr><th>Data e Orario</th><th>Corso</th><th>Livello di Difficoltà</th></tr></thead><tbody>";
            while ($row = $resultSummary->fetch_assoc()){
                $dataPrenotazione = date('d/m/Y H:i', strtotime($row['data_prenotazione']));
                $nomeCorso = htmlspecialchars($row['corso_nome']);
                $livello = htmlspecialchars($row['livello_difficolta']);
                echo "<tr><td>$dataPrenotazione</td><td>$nomeCorso</td><td>$livello</td></tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p>Non hai prenotato nessuna lezione.</p>";
        }
        ?>
    </div>

    <script>
        // Toggle menu extra
        function toggleExtraMenu() {
            const extraMenu = document.getElementById('extra-menu');
            extraMenu.classList.toggle('active');
        }
        // Popup di logout
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
        // Chiusura alert/success
        function closeAlert() {
            document.getElementById("alert-box").style.display = "none";
        }
        function closeSuccess() {
            document.getElementById("success-box").style.display = "none";
        }
    </script>

    <footer>
        <p>&copy; 2025 Body Fit Palestra | Sviluppato da <strong>Riccardo</strong></p>
    </footer>
</body>
</html>
