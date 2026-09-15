<?php
session_start();
include __DIR__ . '/db.php';

if (empty($_SESSION['id_user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 403 Forbidden');
    exit('Accesso negato');
}

$me = (int)$_SESSION['id_user'];

$stmt = $conn->prepare("SELECT is_admin, username FROM users WHERE id_user = ?");
$stmt->bind_param("i", $me);
$stmt->execute();
$stmt->bind_result($is_admin_flag, $admin_username);
$stmt->fetch();
$stmt->close();

if ((int)$is_admin_flag !== 1) {
    header('HTTP/1.1 403 Forbidden');
    exit('Accesso negato');
}

$target_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$text = trim($_POST['testo'] ?? '');

if ($target_id <= 0 || $text === '') {
    header('Location: admin_chat.php?user_id=' . $target_id);
    exit;
}

$stmt = $conn->prepare("SELECT username FROM users WHERE id_user = ?");
$stmt->bind_param("i", $target_id);
$stmt->execute();
$stmt->bind_result($target_username);
$stmt->fetch();
$stmt->close();

if (!$target_username) {
    header('Location: admin_chat.php');
    exit;
}

$path = __DIR__ . '/xml/private_chat.xml';

if (!file_exists($path)) {
    $implementation = new DOMImplementation();
    $dtd = $implementation->createDocumentType('chat', '', '../dtd/private_chat.dtd');
    $dom = $implementation->createDocument(null, 'chat', $dtd);
    $dom->encoding = 'UTF-8';
    $dom->formatOutput = true;
    $dom->save($path);
}

libxml_use_internal_errors(true);
$dom = new DOMDocument('1.0', 'UTF-8');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
if (!$dom->load($path)) {
    $implementation = new DOMImplementation();
    $dtd = $implementation->createDocumentType('chat', '', '../dtd/private_chat.dtd');
    $dom = $implementation->createDocument(null, 'chat', $dtd);
    $dom->encoding = 'UTF-8';
    $dom->formatOutput = true;
}

$root = $dom->documentElement;

$messaggio = $dom->createElement('messaggio');
$messaggio->setAttribute('id', uniqid('m_', true));
$messaggio->setAttribute('mittente', 'admin'); 
$messaggio->setAttribute('destinatario', $target_username);
$messaggio->setAttribute('data', date('Y-m-d H:i:s'));
$messaggio->setAttribute('risposta', '0'); 
$messaggio->setAttribute('sollecito_inviato', '0');
$messaggio->setAttribute('admin_id', $me); 

$testoElem = $dom->createElement('testo');
$testoElem->appendChild($dom->createCDATASection($text));
$messaggio->appendChild($testoElem);

$root->appendChild($messaggio);

$xp = new DOMXPath($dom);
$conds = [
    "//messaggio[@mittente='{$target_username}' and @destinatario='admin' and @risposta='0']"
];
$conds[] = "//messaggio[@mittente='{$target_id}' and @destinatario='admin' and @risposta='0']";

foreach ($conds as $c) {
    foreach ($xp->query($c) as $node) {
        $node->setAttribute('risposta', '1');
    }
}

$tmp = $path . '.tmp';
if ($dom->save($tmp) === false) {
    error_log("invio_mess_admin: errore salvataggio tmp");
} else {
    if (!@rename($tmp, $path)) {
        $content = $dom->saveXML();
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            error_log("invio_mess_admin: errore write fallback");
        }
    }
}

header('Location: admin_chat.php?user_id=' . $target_id);
exit;
?>
