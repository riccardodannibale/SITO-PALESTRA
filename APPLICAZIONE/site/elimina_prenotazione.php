<?php
session_start();
date_default_timezone_set('Europe/Rome');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato']);
    exit;
}
$id_user = (int)$_SESSION['id_user'];

$id_prenotazione = isset($_POST['id_prenotazione']) ? (int)$_POST['id_prenotazione'] : 0;
$id_lezione = isset($_POST['id_lezione']) ? (int)$_POST['id_lezione'] : 0;

if ($id_prenotazione <= 0 || $id_lezione <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dati non validi']);
    exit;
}

require __DIR__ . '/db.php';

$is_admin = false;
$username = null;
if (isset($conn)) {
    $stmt = $conn->prepare("SELECT is_admin, username FROM users WHERE id_user = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id_user);
        $stmt->execute();
        $stmt->bind_result($flag_admin, $db_username);
        $stmt->fetch();
        $stmt->close();
        $is_admin = ($flag_admin == 1);
        $username = $db_username;
    }
}

$lezioniFile = __DIR__ . '/xml/lezioni.xml';
if (!file_exists($lezioniFile)) {
    echo json_encode(['success' => false, 'message' => 'File lezioni non trovato']);
    exit;
}
libxml_use_internal_errors(true);
$lezioniXML = simplexml_load_file($lezioniFile);
if ($lezioniXML === false) {
    echo json_encode(['success' => false, 'message' => 'Errore nel caricamento delle lezioni']);
    exit;
}

$abbonamentiFile = __DIR__ . '/xml/user_abbonamenti.xml';
$id_abbonamento_utente = null;
if (file_exists($abbonamentiFile)) {
    $userAbbXML = simplexml_load_file($abbonamentiFile);
    if ($userAbbXML !== false) {
        $today = date('Y-m-d');
        foreach ($userAbbXML->abbonamento_utente as $abb) {
            $abb_user_id = isset($abb->id_user) ? (int)$abb->id_user : 0;
            $stato = isset($abb->stato) ? (string)$abb->stato : '';
            $data_inizio = isset($abb->data_inizio) ? (string)$abb->data_inizio : '1970-01-01';
            $data_scadenza = isset($abb->data_scadenza) ? (string)$abb->data_scadenza : '1970-01-01';
            if ($abb_user_id === $id_user && $stato === 'attivo' && $today >= $data_inizio && $today <= $data_scadenza) {
                $id_abbonamento_utente = (string)$abb['id'];
                break;
            }
        }
    }
}

$lezioneFound = false;
$prenotazioneFound = false;
foreach ($lezioniXML->lezione as $lezione) {
    if ((int)$lezione['id'] === $id_lezione) {
        $lezioneFound = true;
        if (isset($lezione->prenotanti) && $lezione->prenotanti->count() > 0) {
            foreach ($lezione->prenotanti->prenotante as $p) {
                if ((int)$p['id'] === $id_prenotazione) {
                    $p_id_abb = isset($p['id_abb']) ? (string)$p['id_abb'] : '';
                    $p_user_attr = isset($p['user']) ? (string)$p['user'] : '';
                    $isOwner = false;
                    if ($is_admin) $isOwner = true;
                    if (!$isOwner && $id_abbonamento_utente !== null && $p_id_abb === $id_abbonamento_utente) $isOwner = true;
                    if (!$isOwner && isset($_SESSION['username']) && $_SESSION['username'] !== '' && $p_user_attr === $_SESSION['username']) $isOwner = true;

                    if (!$isOwner) {
                        echo json_encode(['success' => false, 'message' => 'Non hai i permessi per cancellare questa prenotazione']);
                        exit;
                    }

                    $p['stato'] = 'cancellato';
                    $p['data_cancellazione'] = date('Y-m-d H:i:s');
                    $prenotazioneFound = true;
                    break 2;
                }
            }
        }
    }
}

if (!$lezioneFound) {
    echo json_encode(['success' => false, 'message' => 'Lezione non trovata']);
    exit;
}
if (!$prenotazioneFound) {
    echo json_encode(['success' => false, 'message' => 'Prenotazione non trovata o già cancellata']);
    exit;
}

$tmpFile = $lezioniFile . '.tmp';
if ($lezioniXML->asXML($tmpFile) === false) {
    @unlink($tmpFile);
    echo json_encode(['success' => false, 'message' => 'Errore nel salvataggio temporaneo']);
    exit;
}
if (!rename($tmpFile, $lezioniFile)) {
    @unlink($tmpFile);
    echo json_encode(['success' => false, 'message' => 'Errore nel persistere la cancellazione']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Prenotazione cancellata con successo!']);
exit;
