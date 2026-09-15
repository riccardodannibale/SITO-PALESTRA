<?php
session_start();
require_once __DIR__ . '/db.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

if (!isset($_SESSION['id_user'], $_SESSION['username'], $_SESSION['is_admin']) ||
    $_SESSION['username'] !== 'root' || (int)$_SESSION['is_admin'] !== 1) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Accesso non autorizzato.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: superadmin.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['super_status'] = 'Token CSRF non valido.';
    header('Location: superadmin.php');
    exit;
}

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$action  = $_POST['action'] ?? '';

if ($user_id <= 0 || !in_array($action, ['promote', 'revoke'], true)) {
    $_SESSION['super_status'] = 'Dati della richiesta non validi.';
    header('Location: superadmin.php');
    exit;
}

$stmt = $conn->prepare("SELECT id_user, username, is_admin FROM users WHERE id_user = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows !== 1) {
    $stmt->close();
    $_SESSION['super_status'] = 'Utente non trovato.';
    header('Location: superadmin.php');
    exit;
}
$target = $res->fetch_assoc();
$stmt->close();

if ($target['username'] === 'root') {
    $_SESSION['super_status'] = 'Il super admin root non può essere modificato.';
    header('Location: superadmin.php');
    exit;
}

$newFlag = $action === 'promote' ? 1 : 0;
$upd = $conn->prepare("UPDATE users SET is_admin = ? WHERE id_user = ?");
$upd->bind_param("ii", $newFlag, $user_id);
$ok = $upd->execute();
$upd->close();

if ($ok) {
    $_SESSION['super_status'] = $action === 'promote' ? 'Utente promosso ad admin.' : 'Permessi admin revocati all\'utente.';
} else {
    $_SESSION['super_status'] = 'Errore durante l\'aggiornamento.';
}

header('Location: superadmin.php');
exit;
