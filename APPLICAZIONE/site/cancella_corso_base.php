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
    die("Accesso negato: solo gli amministratori possono cancellare corsi base.");
}

if (!isset($_GET['id'])) {
    die("ID corso non specificato.");
}

$id_corso = (int)$_GET['id'];

$corsiBaseFile = __DIR__ . '/xml/corsi_base.xml';
$lezioniFile = __DIR__ . '/xml/lezioni.xml';
$feedbackFile = __DIR__ . '/xml/feedback.xml';

$corsiBaseXML = simplexml_load_file($corsiBaseFile);
if ($corsiBaseXML === false) {
    die("Errore nel caricamento dei corsi base.");
}

$corsoFound = false;
foreach ($corsiBaseXML->corso_base as $corso) {
    if ((int)$corso->attributes()->id === $id_corso) {
        $dom = dom_import_simplexml($corso);
        $dom->parentNode->removeChild($dom);
        $corsoFound = true;
        break;
    }
}

if (!$corsoFound) {
    die("Corso base non trovato.");
}

if (!$corsiBaseXML->asXML($corsiBaseFile)) {
    die("Errore nel salvataggio dei corsi base.");
}

$lezioniDaCancellare = [];
if (file_exists($lezioniFile)) {
    $lezioniXML = simplexml_load_file($lezioniFile);
    if ($lezioniXML !== false) {
        foreach ($lezioniXML->lezione as $lezione) {
            if ((int)$lezione->id_corso_base === $id_corso) {
                $lezioniDaCancellare[] = (int)$lezione->attributes()->id;
                $dom = dom_import_simplexml($lezione);
                $dom->parentNode->removeChild($dom);
            }
        }
        $lezioniXML->asXML($lezioniFile);
    }
}

if (!empty($lezioniDaCancellare) && file_exists($feedbackFile)) {
    $feedbackXML = simplexml_load_file($feedbackFile);
    if ($feedbackXML !== false) {
        foreach ($feedbackXML->valutazione_corso as $feedback) {
            if (in_array((int)$feedback->id_lezione, $lezioniDaCancellare)) {
                $dom = dom_import_simplexml($feedback);
                $dom->parentNode->removeChild($dom);
            }
        }
        $feedbackXML->asXML($feedbackFile);
    }
}

$_SESSION['success_message'] = "Corso base e lezioni associate eliminate con successo!";
header('Location: corsi.php');
exit;
?>