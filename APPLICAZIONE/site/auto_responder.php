<?php
include __DIR__.'/db.php';

$xmlFile = __DIR__.'/xml/private_chat.xml';
if (!file_exists($xmlFile)) {
    error_log("File XML chat non trovato: $xmlFile");
    exit;
}

$doc = new DOMDocument('1.0','UTF-8');
$doc->preserveWhiteSpace = false;
$doc->formatOutput = true;
libxml_use_internal_errors(true);
if (!$doc->load($xmlFile)) {
    foreach (libxml_get_errors() as $err) { error_log($err->message); }
    libxml_clear_errors();
    exit;
}
$root = $doc->documentElement;
$xpath = new DOMXPath($doc);

$now = new DateTimeImmutable('now');

function parseDateTime($str) {
    if (!$str) return false;
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $str);
    if ($dt) return $dt;
    $ts = strtotime($str);
    return $ts ? (new DateTimeImmutable())->setTimestamp($ts) : false;
}

$usersById = [];
$stmt = $conn->prepare("SELECT id_user, username FROM users");
if ($stmt && $stmt->execute()) {
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $usersById[(string)$r['id_user']] = $r['username'];
    }
    $stmt->close();
}

$query = "//messaggio[@destinatario='admin' and not(@sollecito_inviato='1')]";
foreach ($xpath->query($query) as $m) {
    $msgDateStr = $m->getAttribute('data');
    $msgDate = parseDateTime($msgDateStr);
    if ($msgDate === false) continue;

    $seconds = $now->getTimestamp() - $msgDate->getTimestamp();
    if ($seconds < 48*3600) continue;

    $mittenteRaw = (string)$m->getAttribute('mittente'); 
    $mittente = $mittenteRaw;

    if (ctype_digit($mittenteRaw) && isset($usersById[$mittenteRaw])) {
        $mittente = $usersById[$mittenteRaw];
    }

    $escapedMittente = htmlspecialchars($mittente, ENT_QUOTES | ENT_XML1);
    $escapedMittenteRaw = htmlspecialchars($mittenteRaw, ENT_QUOTES | ENT_XML1);

    $replyXpathParts = [];
    $replyXpathParts[] = "//messaggio[@mittente='admin' and @destinatario='{$escapedMittente}']";
    if ($escapedMittenteRaw !== $escapedMittente) {
        $replyXpathParts[] = "//messaggio[@mittente='admin' and @destinatario='{$escapedMittenteRaw}']";
    }

    $hasAdminReply = false;
    foreach ($replyXpathParts as $rxp) {
        foreach ($xpath->query($rxp) as $candidate) {
            $candDate = parseDateTime($candidate->getAttribute('data'));
            if ($candDate && $candDate > $msgDate) {
                $hasAdminReply = true;
                break 2;
            }
        }
    }

    if ($hasAdminReply) {
        $m->setAttribute('sollecito_inviato', '0');
        continue;
    }

    $auto = $doc->createElement('messaggio');
    $auto->setAttribute('id', uniqid('m_', true));
    $auto->setAttribute('mittente', 'admin');
    $destForAuto = $mittente !== '' ? $mittente : $mittenteRaw;
    $auto->setAttribute('destinatario', $destForAuto);
    $auto->setAttribute('data', (new DateTime())->format('Y-m-d H:i:s'));
    $auto->setAttribute('risposta', '0'); 
    $auto->setAttribute('sollecito_inviato', '1'); 
    $auto->setAttribute('admin_id', '0');

    $testo = $doc->createElement('testo');
    $testo->appendChild($doc->createCDATASection(
        "Grazie per averci contattato — al momento i nostri operatori sono occupati. Ti risponderemo al più presto. Se preferisci chiamaci o passa direttamente in struttura."
    ));
    $auto->appendChild($testo);
    $root->appendChild($auto);

    $m->setAttribute('sollecito_inviato', '1');
}

$tmpFile = $xmlFile . '.tmp';
if ($doc->save($tmpFile) === false) {
    error_log("Impossibile salvare XML temporaneo $tmpFile");
    exit;
}
if (!rename($tmpFile, $xmlFile)) {
    $content = $doc->saveXML();
    if (file_put_contents($xmlFile, $content, LOCK_EX) === false) {
        error_log("Errore nel salvataggio finale di $xmlFile");
    }
}

?>
