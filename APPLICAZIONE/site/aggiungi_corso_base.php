<?php
session_start();

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit;
}

include __DIR__ . '/db.php';

$id_user = $_SESSION['id_user'];
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id_user = ?");
$stmt->bind_param("i", $id_user);
$stmt->execute();
$stmt->bind_result($is_admin);
$stmt->fetch();
$stmt->close();

if (!$is_admin) {
    die("Accesso negato: solo gli amministratori possono aggiungere corsi base.");
}

$nome = $_POST['nome'] ?? '';
$descrizione = $_POST['descrizione'] ?? '';
$difficolta = (int)($_POST['difficolta'] ?? 0);

if ($difficolta < 1 || $difficolta > 3) {
    die("Difficoltà non valida. Deve essere tra 1 e 3.");
}

if (empty($nome) || empty($descrizione) || $difficolta === 0) {
    die("Compila tutti i campi obbligatori correttamente.");
}

$xmlFile = __DIR__ . '/xml/corsi_base.xml';

if (file_exists($xmlFile)) {
    $xml = simplexml_load_file($xmlFile);
    if ($xml === false) {
        die("Errore nel caricamento dei corsi base.");
    }
} else {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE corsi_base SYSTEM "../dtd/corsi_base.dtd"><corsi_base></corsi_base>');
}

$maxId = 0;
foreach ($xml->corso_base as $corso) {
    $id = (int)$corso->attributes()->id;
    if ($id > $maxId) $maxId = $id;
}
$newId = $maxId + 1;

$newCorso = $xml->addChild('corso_base');
$newCorso->addAttribute('id', $newId);
$newCorso->addChild('nome', htmlspecialchars($nome));
$newCorso->addChild('descrizione', htmlspecialchars($descrizione));
$newCorso->addChild('difficolta', $difficolta);

$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xml->asXML());

if ($dom->save($xmlFile)) {
    $_SESSION['success_message'] = "Corso base aggiunto con successo!";
    header('Location: corsi.php');
    exit;
} else {
    die("Errore nel salvataggio del corso base.");
}
?>