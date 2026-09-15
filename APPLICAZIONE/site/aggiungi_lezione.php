<?php
session_start();
date_default_timezone_set('Europe/Rome');

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit;
}

require __DIR__ . '/db.php';

$id_user = (int)$_SESSION['id_user'];

$is_admin = false;
if (isset($conn)) {
    $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id_user = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id_user);
        $stmt->execute();
        $stmt->bind_result($flag_admin);
        $stmt->fetch();
        $stmt->close();
        $is_admin = ($flag_admin == 1);
    } else {
        error_log("DB prepare error: " . $conn->error);
    }
}

if (!$is_admin) {
    http_response_code(403);
    die("Accesso negato: solo gli amministratori possono aggiungere lezioni.");
}

$id_corso_base = isset($_POST['id_corso_base']) ? (int)$_POST['id_corso_base'] : 0;
$datetime_lezione = isset($_POST['datetime_lezione']) ? trim($_POST['datetime_lezione']) : '';
$posti_totali = isset($_POST['posti_totali']) ? (int)$_POST['posti_totali'] : 0;

if ($id_corso_base <= 0 || $datetime_lezione === '' || $posti_totali <= 0) {
    die("Compila tutti i campi obbligatori correttamente.");
}

$ts = strtotime($datetime_lezione);
if ($ts === false || $ts === -1) {
    die("Formato data/ora non valido.");
}

$corsiFile = __DIR__ . '/xml/corsi_base.xml';
if (!file_exists($corsiFile)) {
    die("File corsi_base non trovato.");
}
libxml_use_internal_errors(true);
$corsiXML = simplexml_load_file($corsiFile);
if ($corsiXML === false) {
    $errs = libxml_get_errors();
    foreach ($errs as $e) { error_log("XML error corsi_base: " . trim($e->message)); }
    libxml_clear_errors();
    die("Errore nel caricamento dei corsi base.");
}
$foundCorso = false;
foreach ($corsiXML->corso_base as $cb) {
    if ((int)$cb['id'] === $id_corso_base) { $foundCorso = true; break; }
}
if (!$foundCorso) {
    die("Corso base non trovato.");
}

$xmlFile = __DIR__ . '/xml/lezioni.xml';
libxml_use_internal_errors(true);
if (file_exists($xmlFile)) {
    $xml = simplexml_load_file($xmlFile);
    if ($xml === false) {
        $errs = libxml_get_errors();
        foreach ($errs as $e) { error_log("XML error lezioni: " . trim($e->message)); }
        libxml_clear_errors();
        die("Errore nel caricamento delle lezioni.");
    }
} else {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><lezioni></lezioni>');
}

$maxId = 0;
foreach ($xml->lezione as $lezione) {
    $id = (int)$lezione['id'];
    if ($id > $maxId) $maxId = $id;
}
$newId = $maxId + 1;

$newLezione = $xml->addChild('lezione');
$newLezione->addAttribute('id', (string)$newId);
$newLezione->addChild('id_corso_base', (string)$id_corso_base);
$newLezione->addChild('datetime_lezione', date('Y-m-d H:i:s', $ts));
$newLezione->addChild('posti_totali', (string)$posti_totali);
$newLezione->addChild('prenotanti'); 

$tmpFile = $xmlFile . '.tmp';
if ($xml->asXML($tmpFile) === false) {
    @unlink($tmpFile);
    die("Errore nel salvataggio temporaneo della lezione.");
}
if (!rename($tmpFile, $xmlFile)) {
    @unlink($tmpFile);
    die("Errore nel persistere la nuova lezione.");
}

$_SESSION['success_message'] = "Lezione aggiunta con successo!";
header('Location: corsi.php');
exit;
