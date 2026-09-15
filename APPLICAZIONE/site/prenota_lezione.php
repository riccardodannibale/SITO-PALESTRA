<?php
session_start();
date_default_timezone_set('Europe/Rome');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato']);
    exit;
}
$id_user = (int)$_SESSION['id_user'];

$id_lezione = isset($_POST['id_lezione']) ? (int)$_POST['id_lezione'] : 0;
if ($id_lezione <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID lezione non valido']);
    exit;
}

require __DIR__ . '/db.php';

$is_admin = false;
$username_from_db = '';
if (isset($conn)) {
    $stmt = $conn->prepare("SELECT is_admin, username FROM users WHERE id_user = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id_user);
        $stmt->execute();
        $stmt->bind_result($flag_admin, $username_from_db);
        $stmt->fetch();
        $stmt->close();
        $is_admin = ($flag_admin == 1);
    }
}
if ($is_admin) {
    echo json_encode(['success' => false, 'message' => 'Gli admin non possono prenotare lezioni']);
    exit;
}

$abbonamentiFile = __DIR__ . '/xml/user_abbonamenti.xml';
libxml_use_internal_errors(true);
if (!file_exists($abbonamentiFile)) {
    echo json_encode(['success' => false, 'message' => 'File abbonamenti non trovato']);
    exit;
}
$userAbbXML = simplexml_load_file($abbonamentiFile);
if ($userAbbXML === false) {
    echo json_encode(['success' => false, 'message' => 'Errore nel caricamento degli abbonamenti']);
    exit;
}

$today = date('Y-m-d');
$abbonamento_attivo = null;
foreach ($userAbbXML->abbonamento_utente as $abb) {
    $abb_user_id = isset($abb->id_user) ? (int)$abb->id_user : 0;
    $stato = isset($abb->stato) ? (string)$abb->stato : '';
    $data_inizio = isset($abb->data_inizio) ? (string)$abb->data_inizio : '1970-01-01';
    $data_scadenza = isset($abb->data_scadenza) ? (string)$abb->data_scadenza : '1970-01-01';
    if ($abb_user_id === $id_user && $stato === 'attivo' && $today >= $data_inizio && $today <= $data_scadenza) {
        $abbonamento_attivo = $abb;
        break;
    }
}
if (!$abbonamento_attivo) {
    echo json_encode(['success' => false, 'message' => 'Nessun abbonamento attivo']);
    exit;
}
$id_abbonamento = (string)$abbonamento_attivo['id'];

$lezioniFile = __DIR__ . '/xml/lezioni.xml';
if (!file_exists($lezioniFile)) {
    echo json_encode(['success' => false, 'message' => 'File lezioni non trovato']);
    exit;
}
$lezioniXML = simplexml_load_file($lezioniFile);
if ($lezioniXML === false) {
    echo json_encode(['success' => false, 'message' => 'Errore nel caricamento delle lezioni']);
    exit;
}

function get_date_from_lezione_node($node) {
    $candidates = ['data', 'data_ora', 'data_lezione', 'data_inizio', 'datetime', 'data_orario', 'orario'];
    foreach ($candidates as $f) {
        if (isset($node->$f) && trim((string)$node->$f) !== '') {
            return trim((string)$node->$f);
        }
    }
    foreach ($node->attributes() as $attrName => $attrVal) {
        if (stripos($attrName, 'data') !== false && trim((string)$attrVal) !== '') {
            return trim((string)$attrVal);
        }
    }
    return null;
}

$lezione = null;
foreach ($lezioniXML->lezione as $l) {
    if ((int)$l['id'] === $id_lezione) { $lezione = $l; break; }
}
if (!$lezione) {
    echo json_encode(['success' => false, 'message' => 'Lezione non trovata']);
    exit;
}

$max_weekly = 0;
if (isset($abbonamento_attivo->max_prenotazioni_settimanali) &&
    trim((string)$abbonamento_attivo->max_prenotazioni_settimanali) !== '') {
    $max_weekly = (int)$abbonamento_attivo->max_prenotazioni_settimanali;
}

if ($max_weekly > 0) {
    $lesson_date_str = get_date_from_lezione_node($lezione);
    try {
        if ($lesson_date_str) {
            $lesson_date = new DateTime($lesson_date_str, new DateTimeZone('Europe/Rome'));
        } else {
            $lesson_date = new DateTime('now', new DateTimeZone('Europe/Rome'));
        }
    } catch (Exception $e) {
        try {
            $lesson_date = new DateTime(substr($lesson_date_str,0,10), new DateTimeZone('Europe/Rome'));
        } catch (Exception $e2) {
            $lesson_date = new DateTime('now', new DateTimeZone('Europe/Rome'));
        }
    }

    $weekStart = clone $lesson_date;
    $weekStart->setTime(0,0,0);
    $dayOfWeek = (int)$weekStart->format('N'); 
    $weekStart->modify('-'.($dayOfWeek - 1).' days');

    $weekEnd = clone $weekStart;
    $weekEnd->modify('+6 days')->setTime(23,59,59);

    $count = 0;
    foreach ($lezioniXML->lezione as $l) {
        $ldate_str = get_date_from_lezione_node($l);
        if (!$ldate_str) continue;
        try {
            $ld = new DateTime($ldate_str, new DateTimeZone('Europe/Rome'));
        } catch (Exception $e) {
            continue; 
        }
        if ($ld < $weekStart || $ld > $weekEnd) continue;
        if (!isset($l->prenotanti) || $l->prenotanti->count() == 0) continue;
        foreach ($l->prenotanti->prenotante as $p) {
            $p_stato = isset($p['stato']) ? (string)$p['stato'] : 'confermato';
            $p_id_abb = isset($p['id_abb']) ? (string)$p['id_abb'] : '';
            if ($p_stato !== 'cancellato' && $p_id_abb === $id_abbonamento) {
                $count++;
            }
        }
    }

    if ($count >= $max_weekly) {
        echo json_encode([
            'success' => false,
            'message' => "Limite di prenotazioni settimanali raggiunto ({$count}/{$max_weekly})"
        ]);
        exit;
    }
}

$prenotati = 0;
if (isset($lezione->prenotanti) && $lezione->prenotanti->count() > 0) {
    foreach ($lezione->prenotanti->prenotante as $p) {
        $stato = isset($p['stato']) ? (string)$p['stato'] : 'confermato';
        if ($stato !== 'cancellato') $prenotati++;
    }
}
$posti_totali = isset($lezione->posti_totali) ? (int)$lezione->posti_totali : 0;
if ($posti_totali > 0 && $prenotati >= $posti_totali) {
    echo json_encode(['success' => false, 'message' => 'Posti esauriti per questa lezione']);
    exit;
}

if (isset($lezione->prenotanti) && $lezione->prenotanti->count() > 0) {
    foreach ($lezione->prenotanti->prenotante as $p) {
        $p_stato = isset($p['stato']) ? (string)$p['stato'] : 'confermato';
        $p_id_abb = isset($p['id_abb']) ? (string)$p['id_abb'] : '';
        if ($p_stato !== 'cancellato' && $p_id_abb === $id_abbonamento) {
            echo json_encode(['success' => false, 'message' => 'Hai già prenotato questa lezione']);
            exit;
        }
    }
}

$maxId = 0;
if (isset($lezione->prenotanti) && $lezione->prenotanti->count() > 0) {
    foreach ($lezione->prenotanti->prenotante as $p) {
        $pid = isset($p['id']) ? (int)$p['id'] : 0;
        if ($pid > $maxId) $maxId = $pid;
    }
}
$newId = $maxId + 1;

if (!isset($lezione->prenotanti) || $lezione->prenotanti->count() === 0) {
    if (!isset($lezione->prenotanti)) $lezione->addChild('prenotanti');
}

$username = '';
if (isset($_SESSION['username']) && $_SESSION['username'] !== '') {
    $username = $_SESSION['username'];
} elseif ($username_from_db !== '') {
    $username = $username_from_db;
} else {
    $username = 'user_'.$id_user;
}

$prenotante = $lezione->prenotanti->addChild('prenotante');
$prenotante->addAttribute('id', (string)$newId);
$prenotante->addAttribute('id_abb', $id_abbonamento);
$prenotante->addAttribute('user', $username);
$prenotante->addAttribute('stato', 'confermato');
$prenotante->addAttribute('data_prenotazione', date('Y-m-d H:i:s'));

$tmpFile = $lezioniFile . '.tmp';
if ($lezioniXML->asXML($tmpFile) === false) {
    @unlink($tmpFile);
    echo json_encode(['success' => false, 'message' => 'Errore nel salvataggio temporaneo della prenotazione']);
    exit;
}
if (!rename($tmpFile, $lezioniFile)) {
    @unlink($tmpFile);
    echo json_encode(['success' => false, 'message' => 'Errore nel persistere la prenotazione']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Prenotazione effettuata con successo!']);
exit;
?>
