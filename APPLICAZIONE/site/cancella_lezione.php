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
    die("Accesso negato: solo gli amministratori possono cancellare lezioni.");
}

if (!isset($_GET['id'])) {
    die("ID lezione non specificato.");
}

$id_lezione = (int)$_GET['id'];
if ($id_lezione <= 0) {
    die("ID lezione non valido.");
}

$lezioniFile = __DIR__ . '/xml/lezioni.xml';
$feedbackFile = __DIR__ . '/xml/feedback.xml';

libxml_use_internal_errors(true);
if (!file_exists($lezioniFile)) {
    die("File lezioni non trovato.");
}
$lezioniXML = simplexml_load_file($lezioniFile);
if ($lezioniXML === false) {
    $errs = libxml_get_errors();
    foreach ($errs as $e) { error_log("XML error lezioni: " . trim($e->message)); }
    libxml_clear_errors();
    die("Errore nel caricamento delle lezioni.");
}

$lezioneFound = false;
foreach ($lezioniXML->lezione as $lezione) {
    if ((int)$lezione['id'] === $id_lezione) {
        $dom = dom_import_simplexml($lezione);
        $dom->parentNode->removeChild($dom);
        $lezioneFound = true;
        break;
    }
}

if (!$lezioneFound) {
    die("Lezione non trovata.");
}

$tmpLezFile = $lezioniFile . '.tmp';
if ($lezioniXML->asXML($tmpLezFile) === false) {
    @unlink($tmpLezFile);
    die("Errore nel salvataggio temporaneo delle lezioni.");
}
if (!rename($tmpLezFile, $lezioniFile)) {
    @unlink($tmpLezFile);
    die("Errore nel persistere la cancellazione della lezione.");
}

if (file_exists($feedbackFile)) {
    $feedbackXML = simplexml_load_file($feedbackFile);
    if ($feedbackXML !== false) {
        $toRemove = [];
        foreach ($feedbackXML->valutazione_corso as $idx => $feedback) {
            if ((int)$feedback->id_lezione === $id_lezione) {
                $dom = dom_import_simplexml($feedback);
                $dom->parentNode->removeChild($dom);
            }
        }
        $tmpFb = $feedbackFile . '.tmp';
        if ($feedbackXML->asXML($tmpFb) !== false) {
            rename($tmpFb, $feedbackFile);
        } else {
            @unlink($tmpFb);
            error_log("Errore nel salvataggio del file feedback dopo cancellazione lezione id=$id_lezione");
        }
    }
}

$_SESSION['success_message'] = "Lezione eliminata con successo!";
header('Location: corsi.php');
exit;
