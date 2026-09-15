<?php

echo "<h2>Avvio installazione...</h2>";

$baseDir = __DIR__ . "/site";
$xmlDir  = $baseDir . "/xml";
$dtdDir  = $baseDir . "/dtd";


if (!is_dir($xmlDir)) {
    if (!mkdir($xmlDir, 0755, true)) {
        die("Impossibile creare la cartella $xmlDir");
    }
    echo "Cartella xml creata.<br>";
}

$xmlFiles = [
    "abbonamenti.xml"      => ["root" => "abbonamenti",       "dtd" => "abbonamenti.dtd"],
    "admin_audit.xml"      => ["root" => "admin_actions",     "dtd" => "admin_audit.dtd"],
    "comunicazioni.xml"    => ["root" => "comunicazioni",     "dtd" => "comunicazioni.dtd"],
    "corsi_base.xml"       => ["root" => "corsi_base",        "dtd" => "corsi_base.dtd"],
    "faq.xml"              => ["root" => "faqs",              "dtd" => "faq.dtd"],
    "feedback.xml"         => ["root" => "feedback",          "dtd" => "feedback.dtd"],
    "forum.xml"            => ["root" => "forum",             "dtd" => "forum.dtd"],
    "lezioni.xml"          => ["root" => "lezioni",           "dtd" => "lezioni.dtd"],
    "private_chat.xml"     => ["root" => "chat",              "dtd" => "private_chat.dtd"],
    "promo.xml"            => ["root" => "promozioni",        "dtd" => "promo.dtd"],
    "reputazione.xml"      => ["root" => "reputazioni",       "dtd" => "reputazione.dtd"],
    "statistiche.xml"      => ["root" => "statistiche",       "dtd" => "statistiche.dtd"],
    "user_abbonamenti.xml" => ["root" => "user_abbonamenti",  "dtd" => "user_abbonamenti.dtd"],
];

foreach ($xmlFiles as $fileName => $info) {
    $filePath = $xmlDir . "/" . $fileName;

    if (!file_exists($filePath)) {
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL .
                   '<!DOCTYPE ' . $info["root"] . ' SYSTEM "../dtd/' . $info["dtd"] . '">' . PHP_EOL .
                   '<' . $info["root"] . '>' . PHP_EOL .
                   '</' . $info["root"] . '>';

        file_put_contents($filePath, $content);
        echo "Creato: $filePath ✅<br>";
    } else {
        echo "Già esiste: $filePath ✅<br>";
    }
}


require_once $baseDir . '/dati_generali.php';


$conn = new mysqli($host, $user, $password);
if ($conn->connect_error) {
    die("Connessione al DB fallita: " . $conn->connect_error);
}

$sqlFile = __DIR__ . '/fitness_studio.sql';

$sqlContent = file_get_contents($sqlFile);
if ($sqlContent === false) {
    die("Errore nella lettura del file SQL: $sqlFile");
}

$queries = array_filter(array_map('trim', explode(";", $sqlContent)));


$conn->query("SET foreign_key_checks = 0");

foreach ($queries as $query) {
    if (!empty($query)) {
        if (!$conn->query($query)) {
            echo "<div style='color:red'>Errore query: " . htmlspecialchars($conn->error) . "<br><pre>" . htmlspecialchars($query) . "</pre></div>";
        }
    }
}

$conn->query("SET foreign_key_checks = 1");

echo "<br><strong>Database installato con successo ✅</strong><br>";



$reputFile = $xmlDir . '/reputazione.xml';


$candidateTables = ['users','user','utenti','utente','anagrafica','accounts'];

$foundTable = null;
foreach ($candidateTables as $t) {
    $sql = "SELECT COUNT(*) as c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . $conn->real_escape_string($t) . "'";
    $res = $conn->query($sql);
    if ($res) {
        $row = $res->fetch_assoc();
        if ((int)$row['c'] > 0) { $foundTable = $t; break; }
    }
}

function writeReputXml(string $filePath, array $users) {
    $impl = new DOMImplementation();
    $doctype = $impl->createDocumentType('reputazioni', 'SYSTEM', '../dtd/reputazione.dtd');
    $dom = $impl->createDocument(null, 'reputazioni', $doctype);
    $dom->encoding = 'UTF-8';
    $dom->formatOutput = true;

    $root = $dom->documentElement;

    $nowIso = (new DateTime())->format(DateTime::ATOM);
    $today = (new DateTime())->format('Y-m-d');

    foreach ($users as $u) {
        $utente = $dom->createElement('utente');
        $utente->setAttribute('id', (string)$u['id']);

        $username = $dom->createElement('username', $u['username']);
        $utente->appendChild($username);

        $p = $dom->createElement('punteggio', '0.00');
        $p->setAttribute('base', '0.00');
        $p->setAttribute('last_calc', $nowIso);
        $p->setAttribute('last_value', '0.00');
        $utente->appendChild($p);

        $ultima = isset($u['ultima_attivita']) ? $u['ultima_attivita'] : $today;
        $ua = $dom->createElement('ultima_attivita', $ultima);
        $utente->appendChild($ua);

        $root->appendChild($utente);
    }

    $dom->save($filePath);
}

if ($foundTable) {
    $colsRes = $conn->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '" . $conn->real_escape_string($foundTable) . "'");
    $cols = [];
    while ($r = $colsRes->fetch_assoc()) $cols[] = $r['COLUMN_NAME'];

    $idCandidates = ['id','user_id','utente_id'];
    $userCandidates = ['username','user','nome','nome_utente','email','email_address'];

    $idCol = null; $userCol = null;
    foreach ($idCandidates as $c) { if (in_array($c, $cols)) { $idCol = $c; break; } }
    foreach ($userCandidates as $c) { if (in_array($c, $cols)) { $userCol = $c; break; } }

    if (!$idCol && count($cols) > 0) $idCol = $cols[0];
    if (!$userCol && count($cols) > 1) $userCol = $cols[1];

    $users = [];

    if ($idCol && $userCol) {
        $q = "SELECT `" . $conn->real_escape_string($idCol) . "` AS id, `" . $conn->real_escape_string($userCol) . "` AS username FROM `" . $conn->real_escape_string($foundTable) . "`";
        if ($res = $conn->query($q)) {
            while ($r = $res->fetch_assoc()) {
                $users[] = [
                    'id' => $r['id'],
                    'username' => (string)$r['username'],
                ];
            }
        }
    }

    if (count($users) > 0) {
        writeReputXml($reputFile, $users);
        echo "Reputazioni generate per " . count($users) . " utenti in $reputFile ✅<br>";
    } else {
        writeReputXml($reputFile, [ ['id' => 1, 'username' => 'alice'] ]);
        echo "Impossibile estrarre utenti da '$foundTable' — creato file reputazione d'esempio. ($reputFile) ✅<br>";
    }

} else {
    writeReputXml($reputFile, [ ['id' => 1, 'username' => 'alice'] ]);
    echo "Nessuna tabella utenti trovata: creato file reputazione d'esempio ($reputFile) ✅<br>";
}


echo "<h3>Installazione completata ✅</h3>";

$conn->close();

?>
