<?php
// Avvia la sessione per mantenere lo stato utente
session_start();

// Include la connessione al database (variabile $conn)
include __DIR__ . '/db.php';

// Prendo l'id utente dalla sessione se esiste, altrimenti null
$id_user = $_SESSION['id_user'] ?? null;

// Inizializzo variabili di controllo per ruolo e abbonamento
$is_admin = false;
$has_active_abbonamento = false;

// Se l'utente è loggato (id_user esiste)
if ($id_user) {
    // Controlla se l'utente ha un abbonamento attivo (stato='attivo' e data_scadenza >= oggi)
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM user_abbonamenti 
        WHERE id_user = ? AND stato = 'attivo' AND data_scadenza >= CURDATE()
    ");
    $stmt->bind_param("i", $id_user);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();
    // Imposta true se c'è almeno un abbonamento attivo
    $has_active_abbonamento = ($cnt > 0);

    // Controlla se l'utente ha ruolo admin
    $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id_user = ?");
    $stmt->bind_param("i", $id_user);
    $stmt->execute();
    $stmt->bind_result($flag_admin);
    $stmt->fetch();
    $stmt->close();
    $is_admin = ($flag_admin == 1);
}

// Caricamento corsi da file XML

// Abilita gestione errori interni di libxml (evita warning diretti)
libxml_use_internal_errors(true);

$doc = new DOMDocument();
// Rimuove spazi bianchi superflui nel parsing
$doc->preserveWhiteSpace = false;
// Attiva validazione con DTD durante il parsing
$doc->validateOnParse = true;

$xmlFile = __DIR__ . '/xml/corsi.xml';
// Controlla se il file XML esiste e se viene caricato correttamente
if (!file_exists($xmlFile) || !$doc->load($xmlFile)) {
    die("Errore: file XML corsi non trovato o non valido.");
}
// Se la validazione del documento fallisce, stampa gli errori
if (!$doc->validate()) {
    echo "<h2 style='color:red;'>Errori XML/DTD:</h2><ul>";
    foreach (libxml_get_errors() as $e) {
        echo "<li>Line {$e->line}: ", htmlspecialchars($e->message), "</li>";
    }
    echo "</ul>";
    libxml_clear_errors();
}

// Crea oggetto XPath per interrogare l'XML
$xpath = new DOMXPath($doc);
// Seleziona tutti gli elementi <corso> nel file XML
$corsi_xml = $xpath->query('//corso');

// Caricamento date/ora corsi e numero prenotazioni dal DB

$data_ora_corsi = [];
$prenotati_per_corso = [];

// Query per ottenere id corso e data/ora da tabella corsi
$res = $conn->query("SELECT id_corso, datetime_corso FROM corsi");
while($r = $res->fetch_assoc()) {
    $data_ora_corsi[$r['id_corso']] = $r['datetime_corso'];
}
$res->free();

// Query per ottenere numero di prenotazioni per ogni corso
$res = $conn->query("SELECT id_corso, COUNT(*) AS cnt FROM prenotazione GROUP BY id_corso");
while($r = $res->fetch_assoc()) {
    $prenotati_per_corso[$r['id_corso']] = (int)$r['cnt'];
}
$res->free();

// Recupera prenotazioni già fatte dall'utente, se loggato
$prenotazioni_utente = [];
if ($id_user) {
    $stmt = $conn->prepare("SELECT id_corso FROM prenotazione WHERE id_user = ?");
    $stmt->bind_param("i", $id_user);
    $stmt->execute();
    $stmt->bind_result($c);
    while($stmt->fetch()) {
        $prenotazioni_utente[] = $c;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <title>Fitness Studio – Corsi</title>
  <link rel="stylesheet" href="style/corsi.css" />
  <script>
    // Funzione JavaScript per inviare richiesta di prenotazione via fetch API
    function prenotaCorso(id) {
      fetch('prenota_corso.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'id_corso='+encodeURIComponent(id)
      })
      .then(r => r.json())
      .then(d => {
        alert(d.message);
        if(d.success) location.reload();  // ricarica pagina se successo
      })
      .catch(() => alert('Errore rete/server'));
    }
  </script>
</head>
<body>
  <div class="main-header">
    <div class="logo">FITNESS STUDIO</div>
    <ul class="main-menu">
      <li><a href="home_page.php">Home</a></li>
      <?php if (!$id_user): ?>
        <!-- Se non loggato, mostra link per login -->
        <li><a href="login.php">Area Riservata</a></li>
      <?php else: ?>
        <!-- Se loggato, mostra link stato abbonamento e logout -->
        <li><a href="stato_abbonamento.php">Stato abbonamento</a></li>
        <li><a href="logout.php">Logout</a></li>
      <?php endif; ?>
      <li><a href="promo.php">Promo</a></li>
      <li><a href="Chi_Siamo.php">Chi Siamo</a></li>
      <?php if ($is_admin): ?>
        <!-- Se admin, mostra link per aggiungere corso -->
        <li><a href="aggiungi_corso.php">Aggiungi Corso</a></li>
      <?php endif; ?>
    </ul>
  </div>

  <h1>I Nostri Corsi</h1>
  <table>
    <thead>
      <tr>
        <th>Corso</th><th>Descrizione</th><th>Data &amp; Ora</th><th>Prenota</th>
        <?php if($is_admin):?><th>Prenotati</th><th>Elimina Corso</th><?php endif;?>
      </tr>
    </thead>
    <tbody>
      <?php foreach($corsi_xml as $c): 
        // Legge attributi e dati XML di ogni corso
        $id   = $c->getAttribute('id');
        $nome = $c->getElementsByTagName('nome')->item(0)->nodeValue;
        $desc = $c->getElementsByTagName('descrizione')->item(0)->nodeValue;
        $img  = $c->getElementsByTagName('immagine')->item(0)->nodeValue;
        $tot  = (int)$c->getElementsByTagName('posti_totali')->item(0)->nodeValue;
        $pren = $prenotati_per_corso[$id] ?? 0;
        $dt   = $data_ora_corsi[$id] ?? null;
      ?>
      <tr>
        <td>
          <!-- Immagine e nome corso -->
          <img src="img/<?=htmlspecialchars($img)?>" alt="<?=htmlspecialchars($nome)?>" class="corso-img"/>
          <?=htmlspecialchars($nome)?>
        </td>
        <td><?=nl2br(htmlspecialchars($desc))?></td>
        <td><?=$dt ? date('d/m/Y H:i', strtotime($dt)) : 'ND'?></td>
        <td>
          <?php if(!$id_user || !$has_active_abbonamento): ?>
            <!-- Se non loggato o senza abbonamento attivo, mostra messaggio -->
            <em>Login+Abbonamento</em>
          <?php elseif(in_array($id, $prenotazioni_utente)): ?>
            <!-- Se già prenotato mostra messaggio -->
            <strong>Già prenotato</strong>
          <?php else: ?>
            <!-- Pulsante per prenotare -->
            <button class="btn-prenota" onclick="prenotaCorso('<?=htmlspecialchars($id)?>')">Prenota</button>
          <?php endif; ?>
        </td>
        <?php if($is_admin): ?>
        <!-- Colonne admin: mostra posti prenotati e link per eliminare corso -->
        <td><?=$pren?>/<?=$tot?></td>
        <td>
          <a href="cancella_corso.php?id=<?=urlencode($id)?>"
             onclick="return confirm('Eliminare il corso <?=$nome?>?')">❌</a>
        </td>
        <?php endif;?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if($id_user && $has_active_abbonamento): ?>
    <!-- Se utente loggato con abbonamento, mostra le sue prenotazioni -->
    <h2>Le tue prenotazioni</h2>
    <table class="prenotazioni-table">
      <thead>
        <tr><th>Corso</th><th>Data Pren.</th><th>Data &amp; Ora Corso</th><th>Elimina prenot.</th></tr>
      </thead>
      <tbody>
      <?php
      // Query per caricare le prenotazioni dell'utente ordinate per data prenotazione
      $stmt = $conn->prepare("
        SELECT id_prenotazione, id_corso, data_prenotazione
        FROM prenotazione
        WHERE id_user = ?
        ORDER BY data_prenotazione DESC
      ");
      $stmt->bind_param("i", $id_user);
      $stmt->execute();
      $stmt->bind_result($id_pren, $id_corso, $data_pren);
      while($stmt->fetch()):
        // Cerca il nome corso corrispondente all'id corso
        $nome = '';
        foreach($corsi_xml as $_c){
          if($_c->getAttribute('id') === $id_corso){
            $nome = $_c->getElementsByTagName('nome')->item(0)->nodeValue;
            break;
          }
        }
        // Prende data e ora corso se presente
        $dtcor = $data_ora_corsi[$id_corso] ?? null;
      ?>
        <tr>
          <td><?=htmlspecialchars($nome)?></td>
          <td><?=date('d/m/Y H:i', strtotime($data_pren))?></td>
          <td><?=$dtcor ? date('d/m/Y H:i', strtotime($dtcor)) : 'ND'?></td>
          <td>
            <!-- Link per cancellare la prenotazione con conferma -->
            <a href="cancella_prenotazione.php?id=<?=$id_pren?>"
               onclick="return confirm('Annullare prenotazione <?=$nome?>?')">❌</a>
          </td>
        </tr>
      <?php endwhile; $stmt->close(); ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div id="footer"><p>&copy; 2025 FITNESS STUDIO.</p></div>
</body>
</html>
