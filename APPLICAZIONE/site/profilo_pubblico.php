<?php
session_start();
require_once __DIR__ . '/db.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    echo 'ID utente non valido.';
    exit;
}

$stmt = $conn->prepare("SELECT id_user, username, email, is_admin, indirizzo, telefono, reputazione FROM users WHERE id_user = ?");
if (!$stmt) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Errore DB.';
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
    header('HTTP/1.1 404 Not Found');
    echo 'Utente non trovato.';
    exit;
}

$xml_path = __DIR__ . '/xml/reputazione.xml';
$reputazione = $user['reputazione'];
if (file_exists($xml_path) && is_readable($xml_path)) {
    libxml_use_internal_errors(true);
    $sx = simplexml_load_file($xml_path, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
    if ($sx !== false && isset($sx->utente)) {
        foreach ($sx->utente as $xu) {
            $attrs = $xu->attributes();
            $id_attr = (int)($attrs['id'] ?? 0);
            if ($id_attr === (int)$user['id_user']) {
                $punteggio = (string)($xu->punteggio ?? '');
                if (is_numeric($punteggio)) {
                    $reputazione = (float)$punteggio;
                } else {
                    $reputazione = $punteggio;
                }
                break;
            }
        }
    }
    libxml_clear_errors();
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Profilo di <?= h($user['username']) ?></title>
  <link rel="stylesheet" href="style/style_faq.css">
  <style>
    .profile-card { max-width:800px; margin:40px auto; padding:20px; background:#111; color:yellow; border-radius:8px; border:1px solid #333; }
    .profile-row { margin-bottom:10px; }
    .label { color:white; font-size:0.9rem; }
    .value { font-size:1.05rem; }
    .back { margin-top:18px; display:inline-block; color:white; text-decoration:none; }
  </style>
</head>
<body>
  <div class="profile-card">
    <h2>Profilo — <?= h($user['username']) ?></h2>

    <div class="profile-row">
      <div class="label">Username</div>
      <div class="value"><?= h($user['username']) ?></div>
    </div>

    <div class="profile-row">
      <div class="label">Email</div>
      <div class="value"><?= h($user['email']) ?></div>
    </div>

    <?php if (!empty($user['indirizzo'])): ?>
      <div class="profile-row">
        <div class="label">Indirizzo</div>
        <div class="value"><?= h($user['indirizzo']) ?></div>
      </div>
    <?php endif; ?>

    <?php if (!empty($user['telefono'])): ?>
      <div class="profile-row">
        <div class="label">Telefono</div>
        <div class="value"><?= h($user['telefono']) ?></div>
      </div>
    <?php endif; ?>

    <div class="profile-row">
      <div class="label">Admin</div>
      <div class="value"><?= ((int)$user['is_admin'] === 1) ? 'Sì' : 'No' ?></div>
    </div>

    <div class="profile-row">
      <div class="label">Reputazione</div>
      <div class="value"><?= is_numeric($reputazione) ? number_format((float)$reputazione, 2, ',', '') : h((string)$reputazione) ?></div>
    </div>

    <p style="margin-top:18px;">
      <a href="superadmin.php" class="back">← Torna alla gestione </a>
    </p>
  </div>
</body>
</html>
