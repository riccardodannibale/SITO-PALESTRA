<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include __DIR__ . '/db.php';
include __DIR__ . '/audit_log.php';

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? '';
$id = $data['id'] ?? '';
$new_content = $data['content'] ?? '';
$threadId = $data['thread_id'] ?? null;

if (!$type || !$id || $new_content === '') {
    echo json_encode(['success' => false, 'message' => 'Parametri mancanti']);
    exit;
}

$stmt = $conn->prepare("SELECT is_admin, username FROM users WHERE id_user = ?");
$stmt->bind_param("i", $_SESSION['id_user']);
$stmt->execute();
$stmt->bind_result($is_admin_flag, $admin_username);
$stmt->fetch();
$stmt->close();

if (empty($is_admin_flag) || intval($is_admin_flag) !== 1) {
    echo json_encode(['success' => false, 'message' => 'Solo gli admin possono modificare']);
    exit;
}

$_SESSION['username'] = $admin_username;

$xmlFile = __DIR__ . '/xml/forum.xml';
if (!file_exists($xmlFile)) {
    echo json_encode(['success' => false, 'message' => 'forum.xml non trovato']);
    exit;
}

libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->preserveWhiteSpace = false;
$doc->formatOutput = true;
if (!$doc->load($xmlFile)) {
    echo json_encode(['success' => false, 'message' => 'forum.xml malformato']);
    exit;
}

$target = null;
if ($type === 'thread') {
    foreach ($doc->getElementsByTagName('thread') as $t) {
        if ($t->hasAttribute('id') && $t->getAttribute('id') === (string)$id) {
            $target = $t;
            break;
        }
    }
} elseif ($type === 'comment') {
    foreach ($doc->getElementsByTagName('thread') as $t) {
        if ($t->hasAttribute('id') && $t->getAttribute('id') === (string)$threadId) {
            foreach ($t->getElementsByTagName('commento') as $c) {
                if ($c->hasAttribute('id') && $c->getAttribute('id') === (string)$id) {
                    $target = $c;
                    break 2;
                }
            }
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Tipo non valido']);
    exit;
}

if (!$target) {
    echo json_encode(['success' => false, 'message' => 'Elemento non trovato']);
    exit;
}

$old_content_node = $target->getElementsByTagName('contenuto')->item(0);
$old_content = $old_content_node ? $old_content_node->textContent : '';

if ($old_content_node) {
    while ($old_content_node->firstChild) $old_content_node->removeChild($old_content_node->firstChild);
    $old_content_node->appendChild($doc->createTextNode($new_content));
} else {
    $contenutoEl = $doc->createElement('contenuto');
    $contenutoEl->appendChild($doc->createTextNode($new_content));
    $target->appendChild($contenutoEl);
}

$target->setAttribute('last_edit_by', $admin_username);
$target->setAttribute('last_edit_date', date('Y-m-d H:i:s'));

$tmp = $xmlFile . '.tmp';
$xmlString = $doc->saveXML();
if (file_put_contents($tmp, $xmlString, LOCK_EX) === false || !rename($tmp, $xmlFile)) {
    if (file_put_contents($xmlFile, $xmlString, LOCK_EX) === false) {
        echo json_encode(['success' => false, 'message' => 'Errore salvataggio']);
        exit;
    }
}

log_admin_action('edit', $type, $id, [
    'thread_id' => $threadId,
    'old_content' => $old_content,
    'new_content' => $new_content,
    'admin' => $admin_username
]);

echo json_encode(['success' => true]);
exit;
