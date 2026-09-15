<?php
session_start();
if (!isset($_SESSION['id_user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profilo.php');
    exit;
}

$uid = (int)$_SESSION['id_user'];
$testo = trim($_POST['testo'] ?? '');
if ($testo === '') {
    header('Location: profilo.php');
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

require __DIR__ . '/db.php';
$stmt = $conn->prepare("SELECT username FROM users WHERE id_user = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$stmt->bind_result($username_utente);
$stmt->fetch();
$stmt->close();
$conn->close();

if (!$username_utente) {
    $username_utente = (string)$uid;
}

$messaggio = $dom->createElement('messaggio');
$messaggio->setAttribute('id', uniqid('m_', true));
$messaggio->setAttribute('mittente', $username_utente);
$messaggio->setAttribute('destinatario', 'admin');
$messaggio->setAttribute('data', date('Y-m-d H:i:s'));
$messaggio->setAttribute('risposta', '0');
$messaggio->setAttribute('sollecito_inviato', '0');

$testoElem = $dom->createElement('testo');
$testoElem->appendChild($dom->createCDATASection($testo));
$messaggio->appendChild($testoElem);

$root->appendChild($messaggio);

$tmp = $path . '.tmp';
if ($dom->save($tmp) === false) {
    error_log("invio_mess_utente: errore salvataggio tmp");
} else {
    rename($tmp, $path);
}

$dom2 = new DOMDocument();
libxml_use_internal_errors(true);
if ($dom2->load($path)) {
    $xp = new DOMXPath($dom2);
    $nodes = $xp->query("//messaggio[@mittente='admin' and @destinatario='{$username_utente}' and @risposta='0']");
    $changed = false;
    foreach ($nodes as $n) {
        $n->setAttribute('risposta', '1');
        $changed = true;
    }
    if ($changed) {
        $tmp2 = $path . '.tmp2';
        $dom2->save($tmp2);
        rename($tmp2, $path);
    }
}

header('Location: profilo.php');
exit;