<?php
session_start(); // Avvia la sessione per usare variabili come $_SESSION

// Definisce il percorso al file XML che contiene le promozioni
define('XML_FILE', __DIR__ . '/xml/promo.xml');

// Funzione che verifica se l'utente è un amministratore
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}


// GESTIONE AGGIUNTA PROMOZIONE
if (isAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    $doc->load(XML_FILE);

    // Calcolo nuovo ID promozione
    $ids = [];
    foreach ($doc->getElementsByTagName('id_promo') as $elm) {
        $ids[] = (int)$elm->textContent;
    }
    $newId = $ids ? max($ids) + 1 : 1;

    // Crea un nuovo elemento "promozione"
    $promo = $doc->createElement('promozione');

    // Campi della nuova promozione
    $fields = [
        'id_promo'      => $newId,
        'titolo'        => $_POST['titolo'],
        'descrizione'   => $_POST['descrizione'],
        'data_inizio'   => $_POST['data_inizio'],
        'data_fine'     => $_POST['data_fine'],
        'codice_sconto' => $_POST['codice_sconto'],
        'attiva'        => isset($_POST['attiva']) ? 1 : 0,
    ];

    // Aggiunge i campi come nodi figli all'elemento "promozione"
    foreach ($fields as $tag => $val) {
        $child = $doc->createElement($tag, htmlspecialchars($val, ENT_XML1));
        $promo->appendChild($child);
    }

    // Inserisce la nuova promozione nel file XML
    $doc->documentElement->appendChild($promo);
    $doc->save(XML_FILE);

    // Ricarica la pagina per aggiornare la lista promozioni
    header('Location: promo.php');
    exit;
}


// GESTIONE ELIMINAZIONE PROMOZIONE
if (isAdmin() && isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];

    $doc = new DOMDocument();
    $doc->load(XML_FILE);
    $xpath = new DOMXPath($doc);

    // Trova e rimuove la promozione con l'ID specificato
    foreach ($xpath->query("/promo/promozione[id_promo='{$delId}']") as $node) {
        $doc->documentElement->removeChild($node);
    }

    $doc->save(XML_FILE);

    // Ricarica la pagina
    header('Location: promo.php');
    exit;
}

// CARICAMENTO PROMOZIONI
$doc = new DOMDocument();
$doc->load(XML_FILE);
$promos = $doc->getElementsByTagName('promozione');
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="it">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <title>FITNESS STUDIO - Promo</title>
  <link rel="stylesheet" href="style/promo.css" type="text/css" />
</head>
<body>
  <!-- HEADER con menu di navigazione -->
  <div id="header" class="main-header">
    <div class="logo">FITNESS STUDIO</div>
    <ul class="main-menu">
      <?php if (!isset($_SESSION['id_user'])): ?>
        <li><a href="login.php">Area Riservata</a></li>
      <?php else: ?>
        <li><a href="stato_abbonamento.php">Stato abbonamento</a></li>
        <li><a href="logout.php">Logout</a></li>
      <?php endif; ?>
      <li><a href="promo.php">Promo</a></li>
      <li><a href="leggi_corso.php">Corsi</a></li>
      <li><a href="Chi_Siamo.php">Chi Siamo</a></li>
    </ul>
  </div>

  <!-- Titolo pagina -->
  <h1 class="titolo">PROMO</h1>

  <!-- Sezione di avviso -->
  <div class="alert" role="alert" aria-live="polite">
    <p class="attenzione">ATTENZIONE</p>
    <p class="alert-text">Scorri le nostre promozioni!</p>
    <img src="img/allarme.png" alt="Icona allarme promozione" class="alert-img" />
  </div>

  <!-- SEZIONE PROMOZIONI ATTIVE -->
  <div class="container">
    <?php foreach ($promos as $promo): ?>
      <?php if ($promo->getElementsByTagName('attiva')->item(0)->textContent === '1'): ?>
        <?php $id = $promo->getElementsByTagName('id_promo')->item(0)->textContent; ?>
        <div class="promo-item">
          <img src="img/promo<?= $id ?>.png" alt="Promo <?= $id ?>" />
          <div class="promo-content">
            <h2><?= htmlspecialchars($promo->getElementsByTagName('titolo')->item(0)->textContent) ?></h2>
            <p><?= htmlspecialchars($promo->getElementsByTagName('descrizione')->item(0)->textContent) ?></p>
            <p><strong>Periodo:</strong>
               <?= $promo->getElementsByTagName('data_inizio')->item(0)->textContent ?> &mdash;
               <?= $promo->getElementsByTagName('data_fine')->item(0)->textContent ?></p>
            <p><strong>Codice:</strong> <?= htmlspecialchars($promo->getElementsByTagName('codice_sconto')->item(0)->textContent) ?></p>
            <?php if (isAdmin()): ?>
              <!-- Pulsante di eliminazione solo visibile per admin -->
              <a class="delete-btn" href="promo.php?delete=<?= $id ?>" onclick="return confirm('Eliminare questa promozione?')">Elimina</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <!-- FORM DI INSERIMENTO PROMOZIONE (solo per admin) -->
  <?php if (isAdmin()): ?>
  <div class="add-promo-form">
    <h2>Aggiungi Promozione</h2>
    <form method="post" action="promo.php">
      <input type="hidden" name="action" value="add" />
      <label>Titolo:<br><input type="text" name="titolo" required /></label>
      <label>Descrizione:<br><textarea name="descrizione" required></textarea></label>
      <label>Data Inizio:<br><input type="date" name="data_inizio" required /></label>
      <label>Data Fine:<br><input type="date" name="data_fine" required /></label>
      <label>Codice:<br><input type="text" name="codice_sconto" required /></label>
      <label>Attiva: <input type="checkbox" name="attiva" checked /></label>
      <button type="submit">Salva</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- FOOTER -->
  <div id="footer">
    <p>&copy; 2025 FITNESS STUDIO. Tutti i diritti riservati.</p>
  </div>
</body>
</html>
