<?php
session_start(); // Avvia la sessione per gestire dati utente
include __DIR__ . '/db.php'; // Include la connessione al database (percorso assoluto)

$id_user = $_SESSION['id_user'] ?? null; // Prende l'ID utente dalla sessione se esiste
if (!$id_user) { 
    // Se utente non loggato, reindirizza alla pagina di login e termina script
    header('Location: login.php'); 
    exit; 
}

if (!isset($_GET['id'])) 
    // Se non viene passato l'id della prenotazione via GET, interrompe con messaggio
    die('ID prenotazione mancante.');

$id_pren = (int)$_GET['id']; // Converte l'id prenotazione in intero per sicurezza

// Verifica che la prenotazione appartenga all'utente loggato (ownership)
$stmt = $conn->prepare("
  SELECT id_user FROM prenotazione WHERE id_prenotazione=?
");
$stmt->bind_param("i",$id_pren);
$stmt->execute(); 
$stmt->bind_result($owner); 
$stmt->fetch(); 
$stmt->close();

if ($owner !== $id_user) 
    // Se il proprietario della prenotazione non è l'utente loggato, blocca con messaggio
    die('Permessi insufficienti.');

// Procede a cancellare la prenotazione dal database
$stmt = $conn->prepare("DELETE FROM prenotazione WHERE id_prenotazione=?");
$stmt->bind_param("i",$id_pren);
$ok = $stmt->execute(); // Esegue la cancellazione
$stmt->close();

// Dopo la cancellazione, reindirizza alla pagina dei corsi prenotati
header('Location: leggi_corso.php');
