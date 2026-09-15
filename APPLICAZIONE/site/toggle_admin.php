<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include __DIR__ . '/db.php';

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['type'] ?? '';
$id = $data['id'] ?? '';
$status = isset($data['status']) ? intval($data['status']) : 0;

if (!$action || !$id) {
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
    echo json_encode(['success' => false, 'message' => 'Solo gli admin possono effettuare questa operazione']);
    exit;
}

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

$thread = null;
foreach ($doc->getElementsByTagName('thread') as $t) {
    if ($t->hasAttribute('id') && $t->getAttribute('id') === (string)$id) {
        $thread = $t;
        break;
    }
}
if (!$thread) {
    echo json_encode(['success' => false, 'message' => 'Thread non trovato']);
    exit;
}

if ($action === 'pin') {
    $thread->setAttribute('pinned', $status ? 'true' : 'false');
} elseif ($action === 'lock') {
    $thread->setAttribute('locked', $status ? 'true' : 'false');
} else {
    echo json_encode(['success' => false, 'message' => 'Azione non valida']);
    exit;
}

$tmp = $xmlFile . '.tmp';
$xmlString = $doc->saveXML();
if (file_put_contents($tmp, $xmlString, LOCK_EX) === false || !rename($tmp, $xmlFile)) {
    if (file_put_contents($xmlFile, $xmlString, LOCK_EX) === false) {
        echo json_encode(['success' => false, 'message' => 'Errore salvataggio']);
        exit;
    }
}

echo json_encode(['success' => true]);
exit;
