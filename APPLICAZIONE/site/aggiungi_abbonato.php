<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('HTTP/1.1 403 Forbidden');
    exit('Accesso negato');
}

function getXML($path) {
    if (!file_exists($path)) {
        if (strpos($path, 'user_abbonamenti') !== false) {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><user_abbonamenti></user_abbonamenti>');
            $xml->asXML($path);
            return $xml;
        }
        return null;
    }
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($path);
    if ($xml === false) {
        error_log("Errore caricamento XML: " . $path);
        foreach (libxml_get_errors() as $err) error_log($err->message);
        libxml_clear_errors();
        return null;
    }
    return $xml;
}

function saveXML($xml, $path) {
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    return $dom->save($path);
}

function parseDiscountFraction($s) {
    $s = trim((string)$s);
    if ($s === '') return 0.0;
    $s = str_replace(',', '.', $s);
    if (substr($s, -1) === '%') $s = rtrim($s, '%');
    $val = floatval($s);
    if ($val > 1) $val = $val / 100.0;
    return max(0.0, min(1.0, $val));
}

$new_username = trim($_POST['username'] ?? '');
$new_password = trim($_POST['password'] ?? '');
$new_email    = trim($_POST['email'] ?? '');
$new_abbon    = intval($_POST['id_abbonamento'] ?? 0);
$new_promo    = intval($_POST['id_promo'] ?? 0);
$data_inizio  = $_POST['data_inizio'] ?? '';

if ($new_username === '' || $new_password === '' || $new_email === '' || !$new_abbon || $data_inizio === '') {
    $_SESSION['add_error'] = "Compila tutti i campi.";
    header('Location: profilo.php');
    exit;
}

$abbonamentiPath = __DIR__ . '/xml/abbonamenti.xml';
$abbonamentiXML = getXML($abbonamentiPath);
$durata = 0;
$found = false;
$prezzo = '';
$valuta = '';
$prezzo_base = '';

if ($abbonamentiXML) {
    foreach ($abbonamentiXML->abbonamento as $abb) {
        $attr = $abb->attributes();
        if ((int)$attr['id'] == $new_abbon) {
            $durata = (int)$abb->durata_mesi;
            $prezzo_base = (string)$abb->prezzo_base !== '' ? (string)$abb->prezzo_base : (string)$abb->prezzo;
            $prezzo = (string)$abb->prezzo;
            $valuta = (string)$abb->valuta;
            $found = true;
            break;
        }
    }
}

if (!$found) {
    $_SESSION['add_error'] = "Tipo abbonamento non valido.";
    header('Location: profilo.php');
    exit;
}

$promoXML = getXML(__DIR__ . '/xml/promo.xml');
$promoDiscount = 0.0;
if ($new_promo > 0 && $promoXML) {
    foreach ($promoXML->promo as $p) {
        $attr = $p->attributes();
        if ((int)$attr['id'] === $new_promo) {
            $promoDiscount = parseDiscountFraction((string)($p->sconto ?? '0'));
            break;
        }
    }
}

$baseFloat = floatval(str_replace(',', '.', ($prezzo_base !== '' ? $prezzo_base : $prezzo)));
$computedPrice = $baseFloat * (1.0 - $promoDiscount);
$computedPrice = number_format(max(0, $computedPrice), 2, '.', '');

$hash_pass = password_hash($new_password, PASSWORD_DEFAULT);
$conn->begin_transaction();

try {
    $u_stmt = $conn->prepare("
        INSERT INTO users (username, password, email, is_admin) 
        VALUES (?, ?, ?, 0)
    ");
    $u_stmt->bind_param("sss", $new_username, $hash_pass, $new_email);
    
    if (!$u_stmt->execute()) {
        throw new Exception("Errore creazione utente: " . $u_stmt->error);
    }
    
    $new_user_id = $conn->insert_id;
    $u_stmt->close();

    $data_scadenza = $durata > 0
        ? date('Y-m-d', strtotime("$data_inizio +{$durata} month"))
        : date('Y-m-d', strtotime("$data_inizio +7 day"));

    $userAbbPath = __DIR__ . '/xml/user_abbonamenti.xml';
    $userAbbXML = getXML($userAbbPath);
    
    if (!$userAbbXML) {
        $userAbbXML = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><user_abbonamenti></user_abbonamenti>');
    }
    
    $maxId = 0;
    foreach ($userAbbXML->abbonamento_utente as $node) {
        $attr = $node->attributes();
        $idStr = (string)$attr['id'];
        if (preg_match('/(\d+)$/', $idStr, $m)) {
            $num = (int)$m[1];
            if ($num > $maxId) $maxId = $num;
        }
    }
    $newAbbId = 'a' . ($maxId + 1);
    
    $newAbb = $userAbbXML->addChild('abbonamento_utente');
    $newAbb->addAttribute('id', $newAbbId);
    $newAbb->addChild('id_user', $new_user_id);
    $newAbb->addChild('id_abbonamento', $new_abbon);
    $newAbb->addChild('data_inizio', $data_inizio);
    $newAbb->addChild('data_scadenza', $data_scadenza);
    $newAbb->addChild('stato', 'attivo');
    $newAbb->addChild('prezzo', $computedPrice);
    $newAbb->addChild('valuta', $valuta);
    $newAbb->addChild('prezzo_base', number_format($baseFloat, 2, '.', ''));
    
    if ($new_promo > 0) {
        $newAbb->addChild('id_promo', (string)$new_promo);
    }

    if (!saveXML($userAbbXML, $userAbbPath)) {
        throw new Exception("Errore salvataggio XML. Controlla i permessi della cartella /xml");
    }
    
    $conn->commit();
    header('Location: profilo.php');
    exit;
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['add_error'] = $e->getMessage();
    header('Location: profilo.php');
    exit;
}
