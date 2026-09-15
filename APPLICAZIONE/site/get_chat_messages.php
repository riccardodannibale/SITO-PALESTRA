<?php
session_start();
require_once __DIR__ . '/db.php';
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['id_user'])) {
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
if ($is_admin_flag != 1) {
    header('HTTP/1.1 403 Forbidden');
    exit('Accesso negato');
}

$uid = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($uid <= 0) exit('');

$xmlPath = __DIR__ . '/xml/private_chat.xml';
if (!file_exists($xmlPath)) exit('');

libxml_use_internal_errors(true);
$doc = new DOMDocument('1.0', 'UTF-8');
$doc->load($xmlPath);
$xp = new DOMXPath($doc);

$username = null;
$stmt = $conn->prepare("SELECT username FROM users WHERE id_user = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$stmt->bind_result($username);
$stmt->fetch();
$stmt->close();
$username = $username ?: 'Sconosciuto';

$query = "//messaggio[
    (@destinatario = 'admin' and @mittente = '$username') 
    or 
    (@mittente = 'admin' and @destinatario = '$username')
]";

$nodes = $xp->query($query);

$messages = [];
foreach ($nodes as $n) $messages[] = $n;
usort($messages, function($a,$b){ 
    return strtotime($a->getAttribute('data')) <=> strtotime($b->getAttribute('data'));
});

foreach ($messages as $m) {
    $mitt = $m->getAttribute('mittente');
    $ts = date('d/m/Y H:i', strtotime($m->getAttribute('data')));
    $txtNode = $m->getElementsByTagName('testo')->item(0);
    $txt = $txtNode ? $txtNode->textContent : '';
    $is_admin_msg = ($mitt === 'admin');
    $is_unread = !$is_admin_msg && $m->getAttribute('risposta') === '0';
    ?>
    <div class="chat-msg <?= $is_admin_msg ? 'admin' : 'user' ?> <?= $is_unread ? 'unread' : '' ?>">
      <div class="meta">
        <span><?= $is_admin_msg ? 'Staff' : h($username) ?></span>
        <span><?= h($ts) ?></span>
      </div>
      <div class="text"><?= nl2br(h($txt)) ?></div>
    </div>
    <?php
}
?>