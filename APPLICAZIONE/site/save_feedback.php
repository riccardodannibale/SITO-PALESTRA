<?php
session_start();
date_default_timezone_set('Europe/Rome');
header('Content-Type: application/json; charset=utf-8');

function respond($success, $message) {
    echo json_encode(['success' => (bool)$success, 'message' => $message]);
    exit;
}

function parseXmlDate(string $dateStr) {
    $dateStr = trim($dateStr);
    if ($dateStr === '') return false;
    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i',
        'Y-m-d'
    ];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $dateStr);
        if ($dt instanceof DateTime) return $dt;
    }
    $ts = strtotime($dateStr);
    if ($ts === false) return false;
    return (new DateTime())->setTimestamp($ts);
}

function getSimpleXML(string $path) {
    if (!file_exists($path)) return null;
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($path);
    if ($xml === false) {
        foreach (libxml_get_errors() as $err) {
            error_log("Errore parsing XML ($path): " . trim($err->message));
        }
        libxml_clear_errors();
        return null;
    }
    return $xml;
}

if (!isset($_SESSION['id_user'])) {
    respond(false, 'Utente non autenticato');
}
$id_user = (int) $_SESSION['id_user'];

$id_lezione = isset($_POST['id_lezione']) ? (int)$_POST['id_lezione'] : 0;
$voto = isset($_POST['voto']) ? (int)$_POST['voto'] : 0;
if ($id_lezione <= 0 || $voto < 1 || $voto > 5) {
    respond(false, 'Dati non validi');
}

$userAbbPath = __DIR__ . '/xml/user_abbonamenti.xml';
$userAbbXML = getSimpleXML($userAbbPath);
if (!$userAbbXML) {
    respond(false, 'Impossibile verificare abbonamento (file mancante o corrotto).');
}

$today = new DateTime('now', new DateTimeZone('Europe/Rome'));
$abbonamento_attivo = null;
$id_abbonamento = '';

foreach ($userAbbXML->abbonamento_utente as $abb) {
    $abb_id_attr = isset($abb['id']) ? (string)$abb['id'] : '';
    $abb_user_id = isset($abb->id_user) ? (int)$abb->id_user : 0;
    $stato = isset($abb->stato) ? (string)$abb->stato : '';
    $data_inizio_str = isset($abb->data_inizio) ? (string)$abb->data_inizio : '';
    $data_scadenza_str = isset($abb->data_scadenza) ? (string)$abb->data_scadenza : '';

    $data_inizio = parseXmlDate($data_inizio_str);
    $data_scadenza = parseXmlDate($data_scadenza_str);
    if (!$data_inizio || !$data_scadenza) continue;

    if ($abb_user_id === $id_user && $stato === 'attivo' && $today >= $data_inizio && $today <= $data_scadenza) {
        $abbonamento_attivo = $abb;
        $id_abbonamento = $abb_id_attr !== '' ? $abb_id_attr : (string)$abb->id;
        break;
    }
}

if (!$abbonamento_attivo || $id_abbonamento === '') {
    respond(false, 'Nessun abbonamento attivo');
}

$lezioniPath = __DIR__ . '/xml/lezioni.xml';
$lezioniXML = getSimpleXML($lezioniPath);
if (!$lezioniXML) {
    respond(false, 'Errore lettura file lezioni');
}

$foundLezione = null;
foreach ($lezioniXML->lezione as $lez) {
    $lidAttr = isset($lez['id']) ? (int)$lez['id'] : 0;
    if ($lidAttr === $id_lezione) {
        $foundLezione = $lez;
        break;
    }
}
if (!$foundLezione) respond(false, 'Lezione non trovata');

$dt_str = isset($foundLezione->datetime_lezione) ? (string)$foundLezione->datetime_lezione : '';
$dtObj = parseXmlDate($dt_str);
if ($dtObj === false) respond(false, 'Data lezione non valida');

if ($dtObj->getTimestamp() >= time()) {
    respond(false, 'La lezione non è ancora conclusa');
}

$isPartecipante = false;
$prenotanti = $foundLezione->xpath('prenotanti/prenotante') ?: [];
foreach ($prenotanti as $p) {
    $p_id_abb = isset($p['id_abb']) ? (string)$p['id_abb'] : (isset($p->id_abb) ? (string)$p->id_abb : '');
    $stato = isset($p['stato']) ? (string)$p['stato'] : 'confermato';
    if ($p_id_abb !== '' && $p_id_abb === $id_abbonamento && $stato !== 'cancellato') {
        $isPartecipante = true;
        break;
    }
}
if (!$isPartecipante) respond(false, 'Non risulti partecipante a questa lezione');

$feedbackFile = __DIR__ . '/xml/feedback.xml';
$feedbackDir = dirname($feedbackFile);
if (!is_dir($feedbackDir)) {
    if (!@mkdir($feedbackDir, 0755, true)) {
        error_log("Impossibile creare directory $feedbackDir");
    }
}

$fp = @fopen($feedbackFile, 'c+');
if (!$fp) {
    respond(false, 'Impossibile aprire file feedback per scrittura');
}

if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    respond(false, 'Impossibile ottenere lock sul file feedback');
}

fseek($fp, 0);
$contents = stream_get_contents($fp);

libxml_use_internal_errors(true);
$dom = new DOMDocument('1.0', 'UTF-8');
$dom->formatOutput = true;

if ($contents !== false && trim($contents) !== '') {
    if (@$dom->loadXML($contents) === false) {
        foreach (libxml_get_errors() as $err) {
            error_log("Errore parsing feedback.xml: " . trim($err->message));
        }
        libxml_clear_errors();
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('feedback');
        $dom->appendChild($root);
    }
} else {
    $root = $dom->createElement('feedback');
    $dom->appendChild($root);
}

$xpath = new DOMXPath($dom);
$valNodes = $dom->getElementsByTagName('valutazione_corso');

$foundNode = null;
$maxId = 0;
foreach ($valNodes as $node) {
    $attrId = $node->hasAttribute('id') ? (int)$node->getAttribute('id') : 0;
    if ($attrId > $maxId) $maxId = $attrId;


    $childIdLez = null;
    $childIdUserAbb = null;
    foreach ($node->childNodes as $ch) {
        if ($ch->nodeType !== XML_ELEMENT_NODE) continue;
        if ($ch->nodeName === 'id_lezione') $childIdLez = trim($ch->textContent);
        if ($ch->nodeName === 'id_user_abbonamento') $childIdUserAbb = trim($ch->textContent);
    }
    if ($childIdLez !== null && (int)$childIdLez === $id_lezione && $childIdUserAbb !== null && $childIdUserAbb === $id_abbonamento) {
        $foundNode = $node;
        break;
    }
}

if ($foundNode !== null) {
    $votoNode = null;
    foreach ($foundNode->childNodes as $ch) {
        if ($ch->nodeType === XML_ELEMENT_NODE && $ch->nodeName === 'voto') {
            $votoNode = $ch;
            break;
        }
    }
    if ($votoNode) {
        $votoNode->nodeValue = (string)$voto;
    } else {
        $newV = $dom->createElement('voto', (string)$voto);
        $foundNode->appendChild($newV);
    }
    $dataNode = null;
    foreach ($foundNode->childNodes as $ch) {
        if ($ch->nodeType === XML_ELEMENT_NODE && $ch->nodeName === 'data_valutazione') {
            $dataNode = $ch;
            break;
        }
    }
    if ($dataNode) {
        $dataNode->nodeValue = date('Y-m-d H:i:s');
    } else {
        $foundNode->appendChild($dom->createElement('data_valutazione', date('Y-m-d H:i:s')));
    }
} else {
    $newId = $maxId + 1;
    $root = $dom->documentElement;
    if (!$root) {
        $root = $dom->createElement('feedback');
        $dom->appendChild($root);
    }

    $val = $dom->createElement('valutazione_corso');
    $val->setAttribute('id', (string)$newId);

    $val->appendChild($dom->createElement('id_lezione', (string)$id_lezione));
    $val->appendChild($dom->createElement('id_user_abbonamento', $id_abbonamento));
    $val->appendChild($dom->createElement('voto', (string)$voto));
    $val->appendChild($dom->createElement('data_valutazione', date('Y-m-d H:i:s')));

    $root->appendChild($val);
}

$xmlOutput = $dom->saveXML();
if ($xmlOutput === false) {
    flock($fp, LOCK_UN);
    fclose($fp);
    respond(false, 'Errore nella generazione XML');
}

ftruncate($fp, 0);
rewind($fp);
$written = fwrite($fp, $xmlOutput);
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

if ($written === false) {
    respond(false, 'Errore nel salvataggio della valutazione');
}

respond(true, 'Valutazione salvata!');
