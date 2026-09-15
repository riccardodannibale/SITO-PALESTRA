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
if ($uid <= 0) exit('Utente non valido');

$xmlPath = __DIR__ . '/xml/private_chat.xml';
if (!file_exists($xmlPath)) {
    $docInit = new DOMDocument('1.0','UTF-8');
    $root = $docInit->createElement('chat');
    $docInit->appendChild($root);
    $docInit->save($xmlPath);
}

$doc = new DOMDocument('1.0','UTF-8');
$doc->preserveWhiteSpace = false;
$doc->formatOutput = true;
libxml_use_internal_errors(true);
$doc->load($xmlPath);
$xp = new DOMXPath($doc);

$stmt = $conn->prepare("SELECT username FROM users WHERE id_user = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$stmt->bind_result($username);
$stmt->fetch();
$stmt->close();
$username = $username ?: 'Sconosciuto';

$changed = false;
$queryMark = "//messaggio[
    @destinatario = 'admin' 
    and @mittente = '$username' 
    and @risposta = '0'
]";
foreach ($xp->query($queryMark) as $m) {
    $m->setAttribute('risposta', '1');
    $changed = true;
}
if ($changed) {
    $tmp = $xmlPath . '.tmp';
    $doc->save($tmp);
    rename($tmp, $xmlPath);
}

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
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chat con <?= h($username) ?></title>
  <link rel="stylesheet" href="style/style_chat_admin.css">
  <script>
    function sendMessage(event) {
      event.preventDefault();
      const form = event.target;
      const formData = new FormData(form);
      const messageText = formData.get('testo').trim();
      if (!messageText) return;

      fetch('invio_mess_admin.php', {
        method: 'POST',
        body: formData
      }).then(() => {
        loadNewMessages();
        form.reset();
      }).catch(err => console.error(err));
    }

    function loadNewMessages() {
      fetch('get_chat_messages.php?user_id=<?=$uid?>')
        .then(response => response.text())
        .then(data => {
          const chatContainer = document.querySelector('.chat-container');
          if (chatContainer) {
            chatContainer.innerHTML = data;
            chatContainer.scrollTop = chatContainer.scrollHeight;
            const indicator = document.querySelector('.new-messages-indicator');
            if (indicator) indicator.style.display = 'none';
          }
        }).catch(err => console.error(err));
    }

    function checkForNewMessages() {
      fetch('check_new_messages.php?user_id=<?=$uid?>')
        .then(response => response.json())
        .then(data => {
          if (data.unread_count > 0) {
            const indicator = document.querySelector('.new-messages-indicator');
            if (indicator) indicator.style.display = 'block';
          }
        }).catch(err => console.error(err));
    }

    setInterval(loadNewMessages, 3000);
    setInterval(checkForNewMessages, 10000);

    window.addEventListener('load', () => {
      const cb = document.querySelector('.chat-container');
      if (cb) cb.scrollTop = cb.scrollHeight;
    });
  </script>
</head>
<body>
  <header class="main-header">
    <a href="profilo.php" class="back-link">← Torna agli abbonamenti</a>
    <h1>Chat con <?= h($username) ?></h1>
  </header>

  <div class="new-messages-indicator" onclick="loadNewMessages()">Nuovi messaggi! Clicca per aggiornare</div>

  <section class="chat-container" id="chatMessages">
    <?php if (empty($messages)): ?>
      <p class="no-messages">Nessun messaggio nella chat. Inizia la conversazione!</p>
    <?php else: foreach ($messages as $m):
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
    <?php endforeach; endif; ?>
  </section>

  <section class="reply-form">
    <form method="post" onsubmit="sendMessage(event)">
      <input type="hidden" name="user_id" value="<?= $uid ?>">
      <textarea name="testo" placeholder="Scrivi la tua risposta..." required></textarea>
      <button type="submit">Invia</button>
    </form>
  </section>
</body>
</html>