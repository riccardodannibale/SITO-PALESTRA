<?php
session_start(); // Avvia la sessione per accedere ai dati utente
include __DIR__ . '/db.php'; // Include il file di connessione al database

$id_user = $_SESSION['id_user'] ?? null; // Recupera l'ID utente dalla sessione
if (!$id_user) { 
    // Se utente non loggato, reindirizza alla pagina di login e termina script
    header('Location: login.php'); 
    exit; 
}

// Controlla se l'utente è amministratore
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id_user=?");
$stmt->bind_param("i", $id_user);
$stmt->execute(); 
$stmt->bind_result($is_admin); 
$stmt->fetch(); 
$stmt->close();

if (!$is_admin) 
    // Se non è admin, blocca l’accesso con messaggio di permessi insufficienti
    die('Permessi insufficienti.');

if (!isset($_GET['id'])) 
    // Verifica che sia stato passato l'id del corso da eliminare, altrimenti blocca
    die('ID corso mancante.');
$id_corso = $_GET['id']; // Prende l'id del corso da eliminare

// Elimina tutte le prenotazioni collegate al corso (utile se non c’è ON DELETE CASCADE)
$stmt = $conn->prepare("DELETE FROM prenotazione WHERE id_corso=?");
$stmt->bind_param("s", $id_corso);
$stmt->execute(); 
$stmt->close();

// Elimina il corso dal database
$stmt = $conn->prepare("DELETE FROM corsi WHERE id_corso=?");
$stmt->bind_param("s", $id_corso);
$stmt->execute(); 
$stmt->close();

// Carica il file XML contenente i corsi
$xmlPath = __DIR__ . '/xml/corsi.xml';
$doc = new DOMDocument();
$doc->preserveWhiteSpace = false; // Rimuove spazi bianchi inutili
$doc->formatOutput = true; // Formatta l’output in modo leggibile
$doc->load($xmlPath); // Carica il file XML

// Cerca il nodo <corso> con l’attributo id uguale all’id da eliminare
$corsi = $doc->getElementsByTagName('corso');
foreach ($corsi as $corso) {
    if ($corso->getAttribute('id') === $id_corso) {
        // Rimuove il nodo corso trovato dal documento XML
        $corso->parentNode->removeChild($corso);
        break; // Esce dal ciclo una volta trovato ed eliminato
    }
}
// Salva le modifiche al file XML
$doc->save($xmlPath);

// Reindirizza alla pagina di visualizzazione corsi dopo l’eliminazione
header('Location: leggi_corso.php');
exit;
?>
