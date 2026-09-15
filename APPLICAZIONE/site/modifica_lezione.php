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
    die("Accesso negato: solo gli amministratori possono modificare lezioni.");
}

$id_lezione = isset($_POST['id_lezione']) ? (int)$_POST['id_lezione'] : 0;
$datetime_lezione = isset($_POST['datetime_lezione']) ? trim($_POST['datetime_lezione']) : '';
$posti_totali = isset($_POST['posti_totali']) ? (int)$_POST['posti_totali'] : 0;

if ($id_lezione <= 0 || $datetime_lezione === '' || $posti_totali <= 0) {
    die("Dati mancanti o non validi.");
}

$ts = strtotime($datetime_lezione);
if ($ts === false || $ts === -1) {
    die("Formato data/ora non valido.");
}

$xmlFile = __DIR__ . '/xml/lezioni.xml';
libxml_use_internal_errors(true);
if (!file_exists($xmlFile)) {
    die("File lezioni non trovato.");
}
$xml = simplexml_load_file($xmlFile);
if ($xml === false) {
    $errs = libxml_get_errors();
    foreach ($errs as $e) { error_log("XML error: " . trim($e->message)); }
    libxml_clear_errors();
    die("Errore nel caricamento delle lezioni.");
}

$lezioneFound = false;
foreach ($xml->lezione as $lezione) {
    if ((int)$lezione['id'] === $id_lezione) {
        $lezione->datetime_lezione = date('Y-m-d H:i:s', $ts);
        $lezione->posti_totali = $posti_totali;
        $lezioneFound = true;
        break;
    }
}

if (!$lezioneFound) {
    die("Lezione non trovata.");
}

$tmpFile = $xmlFile . '.tmp';
if ($xml->asXML($tmpFile) === false) {
    @unlink($tmpFile);
    die("Errore nel salvataggio temporaneo della lezione.");
}
if (!rename($tmpFile, $xmlFile)) {
    @unlink($tmpFile);
    die("Errore nel persistere la modifica delle lezioni.");
}

$_SESSION['success_message'] = "Lezione modificata con successo!";
header('Location: corsi.php');
exit;
