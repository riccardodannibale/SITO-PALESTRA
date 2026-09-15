<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function respond($ok, $data = []) {
    echo json_encode(array_merge(['success' => (bool)$ok], $data));
    exit;
}

function normalize_username(?string $u): ?string {
    if ($u === null) return null;
    $u = trim($u);
    if ($u === '') return null;
    return mb_strtolower($u, 'UTF-8');
}

function safe_float_from_str($s, $default = 0.0) {
    if ($s === null) return $default;
    $s = trim($s);
    if ($s === '') return $default;
    $s2 = str_replace(',', '.', $s);
    if (is_numeric($s2)) return floatval($s2);
    return $default;
}

$input_raw = file_get_contents('php://input');
$input = json_decode($input_raw, true);
if (!is_array($input)) {
    respond(false, ['message' => 'Payload JSON non valido']);
}

$type = $input['type'] ?? null;
$id   = $input['id'] ?? null;
$thread_id = $input['thread_id'] ?? null;
$provided_penalty = isset($input['rep_penalty']) ? floatval($input['rep_penalty']) : null;

if (!in_array($type, ['comment', 'thread'], true) || !$id) {
    respond(false, ['message' => 'Parametri mancanti o non validi']);
}

$baseDir = __DIR__;
$forumFile = $baseDir . '/xml/forum.xml';
$reputationFile = $baseDir . '/xml/reputazione.xml';
$auditFile = $baseDir . '/xml/admin_audit.xml';


$is_admin = false;
$admin_username = $_SESSION['username'] ?? null;
$id_user = $_SESSION['id_user'] ?? null;
if ($id_user && file_exists($baseDir . '/db.php')) {
    include $baseDir . '/db.php';
    if (isset($conn) && $conn) {
        $stmt = $conn->prepare("SELECT is_admin, username FROM users WHERE id_user = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id_user);
            $stmt->execute();
            $stmt->bind_result($flag_admin, $db_username);
            $stmt->fetch();
            $stmt->close();
            $is_admin = ($flag_admin == 1);
            if (!$admin_username && $db_username) $admin_username = $db_username;
        }
    }
}
if (!$is_admin && isset($_SESSION['is_admin']) && $_SESSION['is_admin']) $is_admin = true;
if (!$is_admin) respond(false, ['message' => 'Accesso negato: privilege admin richiesto']);


libxml_use_internal_errors(true);
$forumDoc = new DOMDocument('1.0','UTF-8');
$forumDoc->preserveWhiteSpace = false;
$forumDoc->formatOutput = true;
if (!file_exists($forumFile) || !$forumDoc->load($forumFile)) {
    respond(false, ['message' => 'Impossibile aprire forum.xml']);
}


$removed = false;
$target_content = '';
$target_author = '';
try {
    if ($type === 'comment') {
        if (!$thread_id) respond(false, ['message' => 'thread_id richiesto per eliminare un commento']);
        $threadNode = null;
        foreach ($forumDoc->getElementsByTagName('thread') as $t) {
            if ($t->hasAttribute('id') && $t->getAttribute('id') === $thread_id) { $threadNode = $t; break; }
        }
        if (!$threadNode) respond(false, ['message' => 'Thread non trovato']);
        $commentsParent = null;
        foreach ($threadNode->childNodes as $cn) {
            if ($cn->nodeName === 'commenti') { $commentsParent = $cn; break; }
        }
        if (!$commentsParent) respond(false, ['message' => 'Nessun elemento commenti nel thread']);
        $commentNode = null;
        foreach ($commentsParent->getElementsByTagName('commento') as $c) {
            if ($c->hasAttribute('id') && $c->getAttribute('id') === $id) { $commentNode = $c; break; }
        }
        if (!$commentNode) respond(false, ['message' => 'Commento non trovato']);

        $authorNode = $commentNode->getElementsByTagName('autore')->item(0);
        $contentNode = $commentNode->getElementsByTagName('contenuto')->item(0);
        $target_author = $authorNode ? trim($authorNode->textContent) : '';
        $target_content = $contentNode ? trim($contentNode->textContent) : '';

        $commentsParent->removeChild($commentNode);
        $removed = true;

    } else {
        $threadNode = null;
        foreach ($forumDoc->getElementsByTagName('thread') as $t) {
            if ($t->hasAttribute('id') && $t->getAttribute('id') === $id) { $threadNode = $t; break; }
        }
        if (!$threadNode) respond(false, ['message' => 'Thread non trovato']);

        $authorNode = $threadNode->getElementsByTagName('autore')->item(0);
        $contentNode = $threadNode->getElementsByTagName('contenuto')->item(0);
        $target_author = $authorNode ? trim($authorNode->textContent) : '';
        $target_content = $contentNode ? trim($contentNode->textContent) : '';

        $forumDoc->documentElement->removeChild($threadNode);
        $removed = true;
    }
} catch (Exception $e) {
    respond(false, ['message' => 'Errore durante rimozione da forum.xml: ' . $e->getMessage()]);
}

try {
    $backupDir = __DIR__ . '/backup/';
    if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);
    $backupPath = $backupDir . '/forum.xml.bak_' . date('Ymd_His') . '_' . uniqid();
    if (!file_exists($backupPath)) copy($forumFile, $backupPath);

    $tmpFile = $forumFile . '.tmp_' . uniqid();
    file_put_contents($tmpFile, $forumDoc->saveXML(), LOCK_EX);
    rename($tmpFile, $forumFile);
} catch (Exception $e) {
    respond(false, ['message' => 'Impossibile salvare forum.xml: ' . $e->getMessage()]);
}


$penalty = $provided_penalty !== null ? floatval($provided_penalty) : ($type === 'comment' ? 2.0 : 5.0);
$rep_changed = false;
$rep_change_amount = 0.0;
$new_base_value = null;

libxml_use_internal_errors(true);
$repDoc = new DOMDocument('1.0','UTF-8');
$repDoc->preserveWhiteSpace = false;
$repDoc->formatOutput = true;
if (!file_exists($reputationFile)) {
    $repDoc->appendChild($repDoc->createElement('reputazioni'));
    file_put_contents($reputationFile, $repDoc->saveXML(), LOCK_EX);
}
if (!$repDoc->load($reputationFile)) {
    respond(false, ['message' => 'Impossibile aprire reputazione.xml']);
}

$foundUserNode = null;
$normalizedTarget = normalize_username($target_author);
foreach ($repDoc->getElementsByTagName('utente') as $u) {
    $unameNode = $u->getElementsByTagName('username')->item(0);
    if (!$unameNode) continue;
    $uname = trim($unameNode->textContent);
    if ($normalizedTarget !== null && normalize_username($uname) === $normalizedTarget) { $foundUserNode = $u; break; }
    if (strcasecmp($uname, $target_author) === 0) { $foundUserNode = $u; break; }
}

if (!$foundUserNode) {
    $maxId = 0;
    foreach ($repDoc->getElementsByTagName('utente') as $u) {
        $idAttr = $u->hasAttribute('id') ? intval($u->getAttribute('id')) : 0;
        if ($idAttr > $maxId) $maxId = $idAttr;
    }
    $newId = $maxId + 1;
    $foundUserNode = $repDoc->createElement('utente');
    $foundUserNode->setAttribute('id', (string)$newId);
    $unameNode = $repDoc->createElement('username', $target_author ?: 'unknown');
    $foundUserNode->appendChild($unameNode);
    $pNode = $repDoc->createElement('punteggio', '0.00');
    $pNode->setAttribute('base', number_format(0.0, 2, '.', ''));
    $foundUserNode->appendChild($pNode);
    $ultimaNode = $repDoc->createElement('ultima_attivita', '');
    $foundUserNode->appendChild($ultimaNode);
    $repDoc->documentElement->appendChild($foundUserNode);
}

$punteggioNode = $foundUserNode->getElementsByTagName('punteggio')->item(0);
if (!$punteggioNode) {
    $punteggioNode = $repDoc->createElement('punteggio', '0.00');
    $punteggioNode->setAttribute('base', number_format(0.0, 2, '.', ''));
    $foundUserNode->appendChild($punteggioNode);
}


$currentBase = 0.0;
if ($punteggioNode->hasAttribute('base')) {
    $currentBase = safe_float_from_str($punteggioNode->getAttribute('base'), 0.0);
} else {
    $currentBase = safe_float_from_str(trim($punteggioNode->textContent ?? ''), 0.0);
}


$newBase = round($currentBase - $penalty, 2);
if ($newBase < 0.0) $newBase = 0.0; 

try {
    $punteggioNode->setAttribute('base', number_format($newBase, 2, '.', ''));
    $now = (new DateTimeImmutable())->format(DATE_ATOM);
    $punteggioNode->setAttribute('last_calc', $now);
    $punteggioNode->setAttribute('last_value', number_format($newBase, 2, '.', ''));
    $punteggioNode->nodeValue = number_format($newBase, 2, '.', '');
    $rep_changed = true;
    $rep_change_amount = $newBase - $currentBase; 
    $new_base_value = $newBase;
} catch (Exception $e) {
    $rep_changed = false;
}

try {
    $backupDir = __DIR__ . '/backup/';
    if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);
    $backupPath = $backupDir . '/reputazione.xml.bak_' . date('Ymd_His') . '_' . uniqid();
    if (!file_exists($backupPath) && file_exists($reputationFile)) copy($reputationFile, $backupPath);

    $tmpFile = $reputationFile . '.tmp_' . uniqid();
    file_put_contents($tmpFile, $repDoc->saveXML(), LOCK_EX);
    rename($tmpFile, $reputationFile);
} catch (Exception $e) {
    respond(false, ['message' => 'Impossibile salvare reputazione.xml: ' . $e->getMessage()]);
}


try {
    $auditDoc = new DOMDocument('1.0','UTF-8');
    $auditDoc->preserveWhiteSpace = false;
    $auditDoc->formatOutput = true;
    if (!file_exists($auditFile)) {
        $root = $auditDoc->createElement('admin_actions');
        $auditDoc->appendChild($root);
        file_put_contents($auditFile, $auditDoc->saveXML(), LOCK_EX);
    }
    if (!$auditDoc->load($auditFile)) {
        $auditDoc = new DOMDocument('1.0','UTF-8');
        $auditDoc->preserveWhiteSpace = false;
        $auditDoc->formatOutput = true;
        $root = $auditDoc->createElement('admin_actions');
        $auditDoc->appendChild($root);
    }
    $root = $auditDoc->documentElement ?: $auditDoc->appendChild($auditDoc->createElement('admin_actions'));

    $action = $auditDoc->createElement('action');
    $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');
    $action->setAttribute('timestamp', $timestamp);
    $action->setAttribute('admin', $admin_username ?? 'unknown');
    $action->setAttribute('type', 'delete');
    $action->setAttribute('target_type', $type === 'comment' ? 'comment' : 'thread');
    $action->setAttribute('target_id', $id);

    $d1 = $auditDoc->createElement('detail', $thread_id ?? ($type === 'thread' ? $id : ''));
    $d1->setAttribute('name', 'thread_id');
    $action->appendChild($d1);

    $d2 = $auditDoc->createElement('detail', $target_content);
    $d2->setAttribute('name', 'content');
    $action->appendChild($d2);

    $d3 = $auditDoc->createElement('detail', $target_author);
    $d3->setAttribute('name', 'author');
    $action->appendChild($d3);

    $d4 = $auditDoc->createElement('detail', $admin_username ?? 'unknown');
    $d4->setAttribute('name', 'admin');
    $action->appendChild($d4);

    $root->appendChild($action);

    $backupPath = $backupDir . '/admin_audit.xml.bak_' . date('Ymd_His') . '_' . uniqid();
    if (!file_exists($backupPath) && file_exists($auditFile)) copy($auditFile, $backupPath);

    $tmpFile = $auditFile . '.tmp_' . uniqid();
    file_put_contents($tmpFile, $auditDoc->saveXML(), LOCK_EX);
    rename($tmpFile, $auditFile);
} catch (Exception $e) {
    
}

if ($rep_changed) {
    $response['rep_change'] = number_format($rep_change_amount, 2, '.', ''); 
    $response['new_base'] = number_format($new_base_value, 2, '.', '');
}

respond(true, $response);
