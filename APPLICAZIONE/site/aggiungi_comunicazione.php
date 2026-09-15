<?php
session_start();
include __DIR__ . '/db.php'; 

$id_user = $_SESSION['id_user'] ?? null;
if (!$id_user) {
    http_response_code(403);
    die('Accesso negato');
}

$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id_user = ?");
$stmt->bind_param("i", $id_user);
$stmt->execute();
$stmt->bind_result($is_admin_flag);
$stmt->fetch();
$stmt->close();

if (!$is_admin_flag) {
    http_response_code(403);
    die('Solo admin possono inserire comunicazioni');
}

$titolo = trim($_POST['titolo'] ?? '');
$contenuto = trim($_POST['contenuto'] ?? '');

if ($titolo === '' || $contenuto === '') {
    header('Location: home_page.php?ok=0');
    exit;
}

$xmlFile = __DIR__ . '/xml/comunicazioni.xml';
libxml_use_internal_errors(true);
$doc = new DOMDocument('1.0','UTF-8');
$doc->preserveWhiteSpace = false;
$doc->formatOutput = true;

$loaded = false;
if (file_exists($xmlFile)) {
    $loaded = $doc->load($xmlFile);
    if (!$loaded) {
        copy($xmlFile, $xmlFile . '.bak_' . date('Ymd_His'));
        $loaded = false;
    }
}

if (!$loaded) {
    $implementation = new DOMImplementation();
    $dtd = $implementation->createDocumentType('comunicazioni', '', '../dtd/comunicazioni.dtd');
    $doc = $implementation->createDocument(null, '', $dtd);
    $doc->encoding = 'UTF-8';
    $root = $doc->createElement('comunicazioni');
    $doc->appendChild($root);
    if(!is_dir(dirname($xmlFile))) mkdir(dirname($xmlFile),0755,true);
}

$root = $doc->getElementsByTagName('comunicazioni')->item(0);
if (!$root) {
    $root = $doc->createElement('comunicazioni');
    $doc->appendChild($root);
}

$msg = $doc->createElement('messaggio');
$msg->setAttribute('autore', (string)$id_user);
$msg->setAttribute('data_invio', date('Y-m-d H:i:s'));

$tit = $doc->createElement('titolo');
$titText = $doc->createTextNode($titolo);
$tit->appendChild($titText);
$cont = $doc->createElement('contenuto');
$contText = $doc->createTextNode($contenuto);
$cont->appendChild($contText);

$msg->appendChild($tit);
$msg->appendChild($cont);

$first = $root->firstChild;
if ($first) {
    $root->insertBefore($msg, $first);
} else {
    $root->appendChild($msg);
}

$xmlString = $doc->saveXML();
if (file_put_contents($xmlFile, $xmlString, LOCK_EX) === false) {
    header('Location: home_page.php?ok=0');
    exit;
}

header('Location: home_page.php?ok=1');
exit;
