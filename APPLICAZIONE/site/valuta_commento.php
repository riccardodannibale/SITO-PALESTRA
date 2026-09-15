<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$forumFile = __DIR__ . '/xml/forum.xml';

function respond($code, $data) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    respond(400, ['success' => false, 'message' => 'Nessun payload ricevuto']);
}
$payload = json_decode($raw, true);
if ($payload === null) {
    respond(400, ['success' => false, 'message' => 'JSON non valido']);
}

if (empty($_SESSION['id_user']) || empty($_SESSION['username'])) {
    respond(401, ['success' => false, 'message' => 'Utente non autenticato']);
}
$id_user = intval($_SESSION['id_user']);
$username = trim($_SESSION['username']);

$thread_id  = isset($payload['thread_id'])  ? trim((string)$payload['thread_id'])  : '';
$comment_id = isset($payload['comment_id']) ? trim((string)$payload['comment_id']) : '';
$utilita    = isset($payload['utilita'])    ? (int)$payload['utilita']            : 0;
$accordo    = isset($payload['accordo'])    ? (int)$payload['accordo']            : 0;

if ($comment_id === '' || $utilita < 1 || $utilita > 5 || $accordo < 1 || $accordo > 5) {
    respond(400, ['success' => false, 'message' => 'Dati di valutazione non validi']);
}

if (!file_exists($forumFile)) {
    respond(500, ['success' => false, 'message' => 'File forum non trovato']);
}

libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->preserveWhiteSpace = false;
$doc->formatOutput = true;
if (!$doc->load($forumFile)) {
    respond(500, ['success' => false, 'message' => 'Impossibile aprire XML forum']);
}

$xpath = new DOMXPath($doc);

$query = sprintf('//commento[@id="%s"]', htmlspecialchars($comment_id, ENT_QUOTES));
$nodes = $xpath->query($query);

if ($nodes->length === 0) {
    foreach ($doc->getElementsByTagName('commento') as $c) {
        $author = $c->getElementsByTagName('autore')->item(0)->textContent ?? '';
        $date   = $c->getElementsByTagName('data')->item(0)->textContent ?? '';
        $maybeId = md5($author . $date);
        if ($maybeId === $comment_id) {
            $nodes = new DOMNodeList(); 
            $targetComment = $c;
            break;
        }
    }
} else {
    $targetComment = $nodes->item(0);
}

if (empty($targetComment) || !($targetComment instanceof DOMElement)) {
    respond(404, ['success' => false, 'message' => 'Commento non trovato']);
}

$authorNode = $targetComment->getElementsByTagName('autore')->item(0);
$commentAuthor = $authorNode ? trim($authorNode->textContent) : '';
if ($commentAuthor !== '' && strcasecmp($commentAuthor, $username) === 0) {
    respond(403, ['success' => false, 'message' => 'Non puoi valutare i tuoi commenti']);
}

$dateNode = $targetComment->getElementsByTagName('data')->item(0);
if ($dateNode) {
    $dateStr = trim($dateNode->textContent);
    if ($dateStr !== '') {
        try {
            $cd = new DateTimeImmutable($dateStr);
            $now = new DateTimeImmutable();
            $diff = $now->diff($cd);
            $days = (int)$diff->days;
            if ($cd > $now) {
                respond(400, ['success' => false, 'message' => 'La data del commento è nel futuro']);
            }
            if ($days > 30) {
                respond(400, ['success' => false, 'message' => 'Finestra di valutazione scaduta (30 giorni)']);
            }
        } catch (Exception $e) {
       
        }
    }
}

$ratingsNode = $targetComment->getElementsByTagName('valutazioni_commento')->item(0);
if (!$ratingsNode) {
    $ratingsNode = $doc->createElement('valutazioni_commento');
    $targetComment->appendChild($ratingsNode);
}

$valElem = $doc->createElement('valutazione');
$valElem->setAttribute('valutatore', $username);

$utilNode = $doc->createElement('utilita', (string)$utilita);
$accordoNode = $doc->createElement('accordo', (string)$accordo);
$valElem->appendChild($utilNode);
$valElem->appendChild($accordoNode);

$valElem->setAttribute('data', (new DateTimeImmutable())->format(DATE_ATOM));

$ratingsNode->appendChild($valElem);


$tmpFile = $forumFile . '.tmp_' . uniqid();
$xmlString = $doc->saveXML();
if ($xmlString === false) {
    respond(500, ['success' => false, 'message' => 'Errore nel serializzare XML']);
}
if (file_put_contents($tmpFile, $xmlString, LOCK_EX) === false) {
    respond(500, ['success' => false, 'message' => 'Errore nella scrittura temporanea']);
}
if (!rename($tmpFile, $forumFile)) {

    @unlink($tmpFile);
    respond(500, ['success' => false, 'message' => 'Impossibile aggiornare il file forum']);
}


$vals = [];
foreach ($ratingsNode->getElementsByTagName('valutazione') as $v) {
    $vals[] = [
        'valutatore' => $v->getAttribute('valutatore'),
        'utilita'    => $v->getElementsByTagName('utilita')->item(0)->textContent ?? '',
        'accordo'    => $v->getElementsByTagName('accordo')->item(0)->textContent ?? ''
    ];
}

respond(200, ['success' => true, 'message' => 'Valutazione aggiunta', 'ratings' => $vals]);
