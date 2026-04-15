<?php
// Connessione al database
include('db.php');

// Avvia la sessione
session_start();

$alertMessage = "";  // Variabile per il messaggio di alert

// Verifica se l'utente è loggato
if (!isset($_SESSION['email'])) {
    header("Location: login_dipendente.php");
    exit;
}

// Recupero il codice fiscale e la tipologia del dipendente loggato
$email = $_SESSION['email'];
$queryCodFiscale = "SELECT codice_fiscale, tipologia_dipendenti FROM dipendente WHERE email = ?";
$stmt = $conn->prepare($queryCodFiscale);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$codiceFiscale = $user['codice_fiscale'] ?? null;
$tipologia = $user['tipologia_dipendenti'] ?? null;  // 3 = receptionist

// Gestione dell'eliminazione della lezione (solo per date a partire da oggi)
if (isset($_POST['delete_lesson'])) {
    $lessonId = $_POST['lesson_id'];
    
    // Recupera i dettagli della lezione da eliminare
    $queryGetLesson = "SELECT cod_corso, data_lezione FROM lezione WHERE id_lezione = ?";
    $stmtGetLesson = $conn->prepare($queryGetLesson);
    $stmtGetLesson->bind_param("i", $lessonId);
    $stmtGetLesson->execute();
    $resultLesson = $stmtGetLesson->get_result();
    
    if ($resultLesson->num_rows > 0) {
        $lesson = $resultLesson->fetch_assoc();
        $cod_corso = $lesson['cod_corso'];
        $data_lezione = $lesson['data_lezione'];

        // Controllo che la lezione sia odierna o futura
        if (strtotime($data_lezione) < strtotime(date('Y-m-d'))) {
            $alertMessage = "Non puoi eliminare una lezione passata.";
        } else {
            // Avvio una transazione per assicurare che entrambe le eliminazioni avvengano
            $conn->begin_transaction();
            try {
                // Estrai la parte data (Y-m-d) della lezione
                $data_lezione_date = date('Y-m-d', strtotime($data_lezione));
                
                // Elimina tutte le prenotazioni relative a questa lezione  
                // Utilizziamo DATE(data_prenotazione) per confrontare solo la parte data
                $queryDeletePrenotazioni = "DELETE FROM prenotazione WHERE cod_corso = ? AND DATE(data_prenotazione) = ?";
                $stmtDeletePrenotazioni = $conn->prepare($queryDeletePrenotazioni);
                $stmtDeletePrenotazioni->bind_param("ss", $cod_corso, $data_lezione_date);
                if (!$stmtDeletePrenotazioni->execute()) {
                    throw new Exception($stmtDeletePrenotazioni->error);
                }
                
                // Elimina la lezione
                $queryDeleteLesson = "DELETE FROM lezione WHERE id_lezione = ?";
                $stmtDeleteLesson = $conn->prepare($queryDeleteLesson);
                $stmtDeleteLesson->bind_param("i", $lessonId);
                if (!$stmtDeleteLesson->execute()) {
                    throw new Exception($stmtDeleteLesson->error);
                }
                
                // Se tutto va bene, confermo la transazione
                $conn->commit();
                $alertMessage = "Lezione eliminata con successo!";
            } catch (Exception $e) {
                $conn->rollback();
                $alertMessage = "Errore nell'eliminazione della lezione: " . $e->getMessage();
            }
        }
    } else {
        $alertMessage = "Lezione non trovata.";
    }
}

// Gestione della prenotazione della lezione
if (isset($_POST['prenota'])) {
    // Verifica che il dipendente sia receptionist
    if ($tipologia != 3) {
        $alertMessage = "Non sei autorizzato a prenotare le lezioni (solo il receptionist può farlo).";
    } else {
        $data = $_POST['data'];
        $orario = $_POST['orario'];
        $iscritto = $_POST['iscritto'];
        $corso = $_POST['corso']; // ID del corso selezionato

        // Combina data e orario per ottenere il datetime completo (in formato Y-m-d H:i:00)
        $dataPrenotazione = $data . ' ' . $orario . ':00';

        // Controlla se l'iscritto ha già prenotato questa lezione
        $queryCheck = "SELECT * FROM prenotazione WHERE cod_iscritto = ? AND cod_corso = ? AND data_prenotazione = ?";
        $stmtCheck = $conn->prepare($queryCheck);
        $stmtCheck->bind_param("sss", $iscritto, $corso, $dataPrenotazione);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        if ($resultCheck->num_rows > 0) {
            $alertMessage = "L'iscritto ha già prenotato questa lezione.";
        } else {
            // Salviamo il codice del receptionist loggato (cod_rec) nella tabella prenotazione
            $queryPrenota = "INSERT INTO prenotazione (cod_iscritto, cod_corso, data_prenotazione, cod_rec) VALUES (?, ?, ?, ?)";
            $stmtPrenota = $conn->prepare($queryPrenota);
            $stmtPrenota->bind_param("ssss", $iscritto, $corso, $dataPrenotazione, $codiceFiscale);
            if ($stmtPrenota->execute()) {
                $alertMessage = "Lezione prenotata con successo!";
            } else {
                $alertMessage = "Errore nella prenotazione. Riprova.";
            }
        }
    }
}

// Gestione inserimento lezione
if (isset($_POST['add_lesson'])) {
    $data = $_POST['data'];
    $orario = $_POST['orario'];
    $corso = $_POST['corso'];
    $insegnante = $_POST['insegnante'];

    $data_completa = $data . ' ' . $orario; // Unione di data e orario
    $queryAddLesson = "INSERT INTO lezione (cod_corso, cod_insegnante, data_lezione) VALUES (?, ?, ?)";
    $stmtAddLesson = $conn->prepare($queryAddLesson);
    $stmtAddLesson->bind_param("sss", $corso, $insegnante, $data_completa);
    if ($stmtAddLesson->execute()) {
        $alertMessage = "Lezione aggiunta con successo!";
    } else {
        $alertMessage = "Errore: " . $stmtAddLesson->error;
    }
}

/* Gestione della settimana visualizzata */
// Leggo il parametro GET "week": 0 = settimana corrente, -1 = precedente, +1 = successiva, ecc.
$weekOffset = isset($_GET['week']) ? intval($_GET['week']) : 0;

// Calcolo del lunedì della settimana corrente in modo robusto
$today = date('Y-m-d');
$dayOfWeek = date('N', strtotime($today)); // 1 = Lunedì, 7 = Domenica
$baseMonday = date('Y-m-d', strtotime("$today -" . ($dayOfWeek - 1) . " days"));
$startOfWeek = date('Y-m-d', strtotime("$baseMonday + $weekOffset week"));
$endOfWeek = date('Y-m-d 23:59:59', strtotime("$startOfWeek +6 days"));

// Recupera tutte le lezioni della settimana selezionata con il conteggio delle prenotazioni e la lista dei prenotati
$query = "
    SELECT 
      l.id_lezione,
      l.data_lezione, 
      c.nome AS corso_nome, 
      c.id_corso,
      c.max_partecipanti,
      CONCAT(d.nome, ' ', d.cognome) AS insegnante_nome,
      COUNT(p.cod_iscritto) AS prenotazioni,
      GROUP_CONCAT(CONCAT(s.nome, ' ', s.cognome) SEPARATOR ', ') AS prenotati_list
    FROM lezione l
    INNER JOIN corso c ON l.cod_corso = c.id_corso
    INNER JOIN insegnante i ON l.cod_insegnante = i.cod_dipendente
    INNER JOIN dipendente d ON i.cod_dipendente = d.codice_fiscale
    LEFT JOIN prenotazione p ON p.cod_corso = c.id_corso AND DATE(p.data_prenotazione) = DATE(l.data_lezione)
    LEFT JOIN iscritto s ON p.cod_iscritto = s.codice_fiscale
    WHERE l.data_lezione BETWEEN ? AND ?
    GROUP BY l.id_lezione, l.data_lezione, c.id_corso, corso_nome, insegnante_nome, c.max_partecipanti
    ORDER BY l.data_lezione
";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $startOfWeek, $endOfWeek);
$stmt->execute();
$result = $stmt->get_result();

$lessons = [];
while ($row = $result->fetch_assoc()) {
    // Calcola l'indice del giorno (0 = lunedì, 6 = domenica)
    $dayIndex = date('N', strtotime($row['data_lezione'])) - 1;
    $time = date('H:i', strtotime($row['data_lezione']));
    $lessons[$dayIndex][$time] = [
        'id_lezione'       => $row['id_lezione'],
        'corso_nome'       => $row['corso_nome'],
        'insegnante_nome'  => $row['insegnante_nome'],
        'id_corso'         => $row['id_corso'],
        'max_partecipanti' => $row['max_partecipanti'],
        'prenotazioni'     => $row['prenotazioni'],
        'prenotati_list'   => $row['prenotati_list']
    ];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Calendario Lezioni</title>
  <link rel="stylesheet" href="style/styles_calendario_dipendente.css">
  <script>
    // Variabile globale per salvare il form da inviare in caso di eliminazione confermata
    var formToDelete = null;

    function openAddLessonPopup(data, orario) {
      document.getElementById("lesson-date").value = data;
      document.getElementById("lesson-time").value = orario;
      document.getElementById("add-lesson-popup").classList.add("show");
    }
    function closeAddLessonPopup() {
      document.getElementById("add-lesson-popup").classList.remove("show");
    }
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
    // Funzione per aprire il popup di conferma eliminazione
    function openDeleteConfirmation(e, form) {
      e.preventDefault();
      formToDelete = form;
      document.getElementById("delete-confirmation-popup").classList.add("show");
      return false;
    }
    function confirmDeletion() {
      if (formToDelete) {
        formToDelete.submit();
      }
      document.getElementById("delete-confirmation-popup").classList.remove("show");
    }
    function cancelDeletion() {
      document.getElementById("delete-confirmation-popup").classList.remove("show");
      formToDelete = null;
    }
    // Auto-dismiss dell'alert dopo 3 secondi
    document.addEventListener("DOMContentLoaded", function(){
      var alertBox = document.getElementById("alert-message");
      if(alertBox){
         setTimeout(function(){
           alertBox.style.opacity = 0;
           setTimeout(function(){ alertBox.remove(); }, 500);
         }, 3000);
      }
    });
  </script>
 
</head>
<body>

<header>
  <nav>
    <div class="logo">
      <img src="style/img/1.png" alt="Left Image" class="logo-image">
      <span>BODY <span>FIT</span></span>
      <img src="style/img/2.png" alt="Right Image" class="logo-image">
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

<?php 
if (!empty($alertMessage)) {
    // Se il messaggio inizia con "Errore", uso il box d'errore; altrimenti, quello di successo.
    $alertClass = (strpos($alertMessage, "Errore") === 0) ? "alert-box" : "success-box";
    echo '<div id="alert-message" class="' . $alertClass . ' show">' . $alertMessage . '</div>';
}
?>

<div class="calendar">
  <h2>Calendario Lezioni</h2>
  
  <!-- Navigazione del calendario -->
  <div class="calendar-navigation">
    <a href="?week=<?php echo $weekOffset - 1; ?>">&larr; Settimana Precedente</a>
    <?php if ($weekOffset !== 0): ?>
      <a href="?week=0">Settimana Corrente</a>
    <?php endif; ?>
    <a href="?week=<?php echo $weekOffset + 1; ?>">Settimana Successiva &rarr;</a>
    <p>Visualizzazione: dal <?php echo date('d/m', strtotime($startOfWeek)); ?> al <?php echo date('d/m', strtotime("$startOfWeek +6 days")); ?></p>
  </div>
  
  <table>
    <thead>
      <tr>
        <th>Orario</th>
        <?php
          $giorni = ["Lunedì", "Martedì", "Mercoledì", "Giovedì", "Venerdì", "Sabato", "Domenica"];
          for ($i = 0; $i < 7; $i++) {
              $cellDate = date('Y-m-d', strtotime("$startOfWeek +$i days"));
              $displayDate = date('d/m', strtotime($cellDate));
              $highlightClass = ($cellDate === date('Y-m-d')) ? 'highlight-column' : '';
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
              $cellDate = date('Y-m-d', strtotime("$startOfWeek +$i days"));
              echo "<td class='lesson-cell'>";
              
              // Se la data della cella è antecedente a oggi, disabilito le azioni
              if (strtotime($cellDate) < strtotime(date('Y-m-d'))) {
                  if (isset($lessons[$i][$hour])) {
                      echo $lessons[$i][$hour]['corso_nome'] . ' (' . $lessons[$i][$hour]['insegnante_nome'] . ')';
                      echo "<div class='booking-info'>";
                      echo $lessons[$i][$hour]['prenotazioni'] . "/" . $lessons[$i][$hour]['max_partecipanti'];
                      if (!empty($lessons[$i][$hour]['prenotati_list'])) {
                          echo "<div class='tooltip'>";
                          $prenotati = explode(', ', $lessons[$i][$hour]['prenotati_list']);
                          foreach ($prenotati as $prenotato) {
                              echo "<div>" . htmlspecialchars($prenotato) . "</div>";
                          }
                          echo "</div>";
                      }
                      echo "</div>";
                      echo "<form method='POST' style='display:inline; margin-top:5px;'>
                                <input type='hidden' name='data' value='$cellDate'>
                                <input type='hidden' name='orario' value='$hour'>
                                <input type='hidden' name='corso' value='{$lessons[$i][$hour]['id_corso']}'>
                                <label>Seleziona Iscritto:</label>
                                <select name='iscritto' disabled>
                                    <option>Non disponibile</option>
                                </select>
                                <button type='submit' name='prenota' class='book-button disabled' disabled>Prenota</button>
                            </form>";
                  } else {
                      echo "<button type='button' class='add-lesson-button' disabled>Aggiungi Lezione</button>";
                  }
              } else {
                  // Per oggi e giorni futuri, abilito le azioni
                  if (isset($lessons[$i][$hour])) {
                      echo $lessons[$i][$hour]['corso_nome'] . ' (' . $lessons[$i][$hour]['insegnante_nome'] . ')';
                      echo "<div class='booking-info'>";
                      echo $lessons[$i][$hour]['prenotazioni'] . "/" . $lessons[$i][$hour]['max_partecipanti'];
                      if (!empty($lessons[$i][$hour]['prenotati_list'])) {
                          echo "<div class='tooltip'>";
                          $prenotati = explode(', ', $lessons[$i][$hour]['prenotati_list']);
                          foreach ($prenotati as $prenotato) {
                              echo "<div>" . htmlspecialchars($prenotato) . "</div>";
                          }
                          echo "</div>";
                      }
                      echo "</div>";
                      if ($lessons[$i][$hour]['prenotazioni'] >= $lessons[$i][$hour]['max_partecipanti']) {
                          $disableAttr = "disabled";
                          $buttonClass = "book-button disabled";
                      } else {
                          $disableAttr = "";
                          $buttonClass = "book-button";
                      }
                      echo "<form method='POST' style='display:inline; margin-top:5px;'>
                                <input type='hidden' name='data' value='$cellDate'>
                                <input type='hidden' name='orario' value='$hour'>
                                <input type='hidden' name='corso' value='{$lessons[$i][$hour]['id_corso']}'>
                                <label>Seleziona Iscritto:</label>
                                <select name='iscritto' required>
                                    <option value=''>Seleziona Iscritto</option>";
                                    // Query aggiornata per selezionare solo gli iscritti con abbonamento attivo e pacchetto 1 o 3
                                    $queryIscritti = "SELECT DISTINCT i.codice_fiscale, i.nome, i.cognome
                                                      FROM iscritto i
                                                      JOIN iscrizione isc ON i.codice_fiscale = isc.cod_iscritto
                                                      WHERE (isc.data_fine IS NULL OR isc.data_fine >= CURDATE())
                                                        AND isc.cod_pacchetto IN (1, 3)
                                                      ORDER BY i.nome ASC";
                                    $stmtIscritti = $conn->prepare($queryIscritti);
                                    $stmtIscritti->execute();
                                    $resultIscritti = $stmtIscritti->get_result();
                                    while ($row = $resultIscritti->fetch_assoc()) {
                                        echo "<option value='" . $row['codice_fiscale'] . "'>" . $row['nome'] . " " . $row['cognome'] . "</option>";
                                    }
                      echo "      </select>";
                      
                      // Se il dipendente è receptionist, abilito il pulsante di prenotazione
                      if ($tipologia == 3) {
                          echo "<button type='submit' name='prenota' class='$buttonClass' $disableAttr>Prenota</button>";
                      } else {
                          echo "<button type='button' class='book-button disabled' disabled>Non autorizzato</button>";
                      }
                      
                      echo "</form>";
                      // FORM di eliminazione lezione (con hidden input per delete_lesson)
                      echo "<form method='POST' style='display:inline; margin-top:5px;'>
                                <input type='hidden' name='lesson_id' value='{$lessons[$i][$hour]['id_lezione']}'>
                                <input type='hidden' name='delete_lesson' value='1'>
                                <button type='button' class='delete-lesson-button' onclick='openDeleteConfirmation(event, this.form);'>Elimina Lezione</button>
                            </form>";
                  } else {
                      echo "<button type='button' onclick='openAddLessonPopup(\"$cellDate\", \"$hour\");' class='add-lesson-button'>Aggiungi Lezione</button>";
                  }
              }
              echo "</td>";
          }
          echo "</tr>";
      }
      ?>
    </tbody>
  </table>
</div>

<!-- Popup per aggiungere lezione -->
<div id="add-lesson-popup" class="popup-overlay">
    <div class="popup-box">
        <h2>Aggiungi lezione</h2>
        <form method="POST">
            <input type="hidden" name="data" id="lesson-date" required>
            <input type="hidden" name="orario" id="lesson-time" required>
            
            <label for="corso">Seleziona Corso:</label>
            <select name="corso" required>
                <option value="">Seleziona Corso</option>
                <?php
                $queryCorsi = "SELECT * FROM corso";
                $stmtCorsi = $conn->prepare($queryCorsi);
                $stmtCorsi->execute();
                $resultCorsi = $stmtCorsi->get_result();
                while ($row = $resultCorsi->fetch_assoc()) {
                    echo "<option value='" . $row['id_corso'] . "'>" . $row['nome'] . "</option>";
                }
                ?>
            </select>
            
            <label for="insegnante">Seleziona Insegnante:</label>
            <select name="insegnante" required>
                <option value="">Seleziona Insegnante</option>
                <?php
                $queryInsegnanti = "SELECT d.codice_fiscale, d.nome, d.cognome FROM dipendente d INNER JOIN insegnante i ON d.codice_fiscale = i.cod_dipendente";
                $stmtInsegnanti = $conn->prepare($queryInsegnanti);
                $stmtInsegnanti->execute();
                $resultInsegnanti = $stmtInsegnanti->get_result();
                while ($row = $resultInsegnanti->fetch_assoc()) {
                    echo "<option value='" . $row['codice_fiscale'] . "'>" . $row['nome'] . " " . $row['cognome'] . "</option>";
                }
                ?>
            </select>
            
            <button type="submit" name="add_lesson">Aggiungi</button>
        </form>
        <br>
        <button onclick="closeAddLessonPopup();" class="btn cancel">Annulla</button>
    </div>
</div>

<!-- Popup di conferma logout -->
<div id="logout-popup" class="popup-overlay">
    <div class="popup-box">
        <h2>Conferma Logout</h2>
        <p>Sei sicuro di voler effettuare il logout?</p>
        <div class="popup-buttons">
            <button class="btn confirm" onclick="confirmLogout();">Sì, esci</button>
            <button class="btn cancel" onclick="closeLogoutPopup();">Annulla</button>
        </div>
    </div>
</div>

<!-- Popup di conferma eliminazione lezione -->
<div id="delete-confirmation-popup" class="popup-overlay">
    <div class="popup-box">
        <h2>Conferma Eliminazione</h2>
        <p>Sei sicuro di voler eliminare questa lezione?</p>
        <div class="popup-buttons">
            <button class="btn confirm" onclick="confirmDeletion();">Sì, elimina</button>
            <button class="btn cancel" onclick="cancelDeletion();">Annulla</button>
        </div>
    </div>
</div>

<footer>
    <p>&copy; 2025 Body Fit Palestra | Sviluppato da <strong>Riccardo</strong></p>
</footer>
</body>
</html>
