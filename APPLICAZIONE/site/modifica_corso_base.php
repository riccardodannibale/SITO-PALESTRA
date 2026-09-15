<?php
session_start();

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit;
}

include __DIR__ . '/db.php';

$id_user = $_SESSION['id_user'];
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id_user = ?");
$stmt->bind_param("i", $id_user);
$stmt->execute();
$stmt->bind_result($is_admin);
$stmt->fetch();
$stmt->close();

if (!$is_admin) {
    die("Accesso negato: solo gli amministratori possono modificare corsi base.");
}

$id = (int)($_POST['id_corso_base'] ?? 0); 
$nome = trim($_POST['nome'] ?? '');
$descrizione = trim($_POST['descrizione'] ?? '');
$difficolta = $_POST['difficolta'] ?? '';

$errori = [];
if ($id <= 0) $errori[] = "ID corso non valido";
if (empty($nome)) $errori[] = "Nome obbligatorio";
if (empty($descrizione)) $errori[] = "Descrizione obbligatoria";
if (empty($difficolta)) {
    $errori[] = "Difficoltà obbligatoria";
} else {
    $difficolta = (int)$difficolta;
    if ($difficolta < 1 || $difficolta > 3) {
        $errori[] = "Difficoltà deve essere tra 1 e 3";
    }
}

if (!empty($errori)) {
    die("Errore: " . implode(", ", $errori));
}

$xmlFile = __DIR__ . '/xml/corsi_base.xml';

if (!file_exists($xmlFile)) {
    die("File corsi base non trovato.");
}

$xml = simplexml_load_file($xmlFile);
if ($xml === false) {
    die("Errore nel caricamento dei corsi base.");
}

$corsoFound = false;
foreach ($xml->corso_base as $corsoBase) {
    if ((int)$corsoBase->attributes()->id == $id) {
        $corsoBase->nome = htmlspecialchars($nome);
        $corsoBase->descrizione = htmlspecialchars($descrizione);
        $corsoBase->difficolta = $difficolta;
        $corsoFound = true;
        break;
    }
}

if (!$corsoFound) {
    die("Corso base non trovato.");
}

$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xml->asXML());

if ($dom->save($xmlFile)) {
    $_SESSION['success_message'] = "Corso base modificato con successo!";
    header('Location: corsi.php');
    exit;
} else {
    die("Errore nel salvataggio del corso base.");
}
?>