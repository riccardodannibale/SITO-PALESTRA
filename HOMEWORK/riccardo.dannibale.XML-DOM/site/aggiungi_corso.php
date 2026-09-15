<?php
session_start(); // Avvia la sessione per gestire dati utente
include __DIR__ . '/db.php'; // Include la connessione al database

// Verifica se utente è loggato
if (!isset($_SESSION['id_user'])) {
    header('Location: login.php'); // Se non loggato, reindirizza al login
    exit;
}

// Prepara query per verificare se l'utente è admin
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id_user = ?");
$stmt->bind_param("i", $_SESSION['id_user']);
$stmt->execute();
$stmt->bind_result($is_admin);
$stmt->fetch();
$stmt->close();

// Se non è admin, blocca accesso
if ($is_admin != 1) {
    die('Accesso negato.');
}

$error = ''; // Variabile per memorizzare eventuali errori

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Raccoglie dati inviati dal form (puliti con trim)
    $nome           = trim($_POST['nome']);
    $desc           = trim($_POST['descrizione']);
    $durata         = intval($_POST['durata_lezione']);
    $posti_totali   = intval($_POST['posti_totali']);
    $immagine       = trim($_POST['immagine']);
    $dt_local       = trim($_POST['datetime_corso']); // Data e ora in formato ISO 8601

    // Controllo validazione minima: campi obbligatori
    if (empty($nome) || empty($desc) || !$durata || !$posti_totali || empty($dt_local)) {
        $error = 'Tutti i campi sono obbligatori.';
    } else {
        // Converte la data/ora da input HTML in formato SQL (YYYY-MM-DD HH:MM:SS)
        $datetime_sql = date('Y-m-d H:i:00', strtotime($dt_local));
        $datetime_xml = $datetime_sql; // stesso formato per XML

        // Carica file XML dei corsi
        $xmlPath = __DIR__ . '/xml/corsi.xml';
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false; // rimuove spazi inutili
        $doc->formatOutput = true;        // formatta output leggibile
        if (!$doc->load($xmlPath)) {
            die('Impossibile caricare XML corsi.');
        }
        $root = $doc->documentElement; // elemento radice <corsi>

        // Calcola nuovo ID corso incrementale (c1, c2, c3, ...)
        $max = 0;
        foreach ($doc->getElementsByTagName('corso') as $c) {
            $i = intval(substr($c->getAttribute('id'), 1)); // estrae la parte numerica da "cX"
            if ($i > $max) $max = $i;
        }
        $newId = 'c' . ($max + 1); // nuovo id

        // Crea nuovo nodo <corso> e aggiunge attributi e figli
        $node = $doc->createElement('corso');
        $node->setAttribute('id', $newId);

        // Per ogni dato crea elemento XML figlio e aggiunge testo escapato
        foreach ([
            'nome'             => $nome,
            'descrizione'      => $desc,
            'durata_lezione'   => $durata,
            'posti_totali'     => $posti_totali,
            'immagine'         => $immagine,
            'datetime_lezione' => $datetime_xml
        ] as $tag => $val) {
            $el = $doc->createElement($tag, htmlspecialchars($val, ENT_XML1));
            $node->appendChild($el);
        }

        $root->appendChild($node); // aggiunge il nuovo corso al documento
        $doc->save($xmlPath);      // salva il file XML modificato

        // Inserisce il corso anche nel database MySQL
        $stmt = $conn->prepare(
            "INSERT INTO corsi (id_corso, nome_corso, descrizione, durata_lezione, datetime_corso, posti_totali)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssssi", $newId, $nome, $desc, $durata, $datetime_sql, $posti_totali);

        if (!$stmt->execute()) {
            // Se errore, termina con messaggio
            die('Errore inserimento DB: ' . $stmt->error);
        }
        $stmt->close();

        // Reindirizza alla pagina di visualizzazione corsi dopo il salvataggio
        header('Location: leggi_corso.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Aggiungi Corso - Fitness Studio</title>
  <link rel="stylesheet" href="style/corsi.css">
</head>
<body>
  <div class="main-header">
    <div class="logo">FITNESS STUDIO</div>
    <ul class="main-menu">
      <li><a href="home_page.php">Home</a></li>
      <li><a href="leggi_corso.php">Corsi</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </div>

  <h1>Aggiungi Nuovo Corso</h1>
  <?php if ($error): ?>
    <!-- Mostra messaggio di errore se presente -->
    <p class="error"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <!-- Form per inserimento dati corso -->
  <form method="post">
    <label>Nome del Corso:<br>
      <input type="text" name="nome" required>
    </label><br><br>

    <label>Descrizione:<br>
      <textarea name="descrizione" rows="4" required></textarea>
    </label><br><br>

    <label>Durata (minuti):<br>
      <input type="number" name="durata_lezione" min="1" required>
    </label><br><br>

    <label>Posti Totali:<br>
      <input type="number" name="posti_totali" min="1" required>
    </label><br><br>

    <label>Nome file immagine:<br>
      <input type="text" name="immagine" value="default.png" required>
    </label><br><br>

    <label>Data e Ora Corso:<br>
      <input type="datetime-local" name="datetime_corso" required>
    </label><br><br>

    <button type="submit">Salva Corso</button>
  </form>

  <div id="footer">
    <p>&copy; 2025 FITNESS STUDIO.</p>
  </div>
</body>
</html>
