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

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$status_message = $_SESSION['super_status'] ?? '';
unset($_SESSION['super_status']);

$reputations = [];
$xml_path = __DIR__ . '/xml/reputazione.xml';
if (file_exists($xml_path) && is_readable($xml_path)) {
    libxml_use_internal_errors(true);
    $sx = simplexml_load_file($xml_path, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
    if ($sx !== false && isset($sx->utente)) {
        foreach ($sx->utente as $xu) {
            $attrs = $xu->attributes();
            $id_attr = (string)($attrs['id'] ?? '');
            if ($id_attr !== '') {
                $punteggio = (string)($xu->punteggio ?? '');
                $reputations[(int)$id_attr] = is_numeric($punteggio) ? (float)$punteggio : $punteggio;
            }
        }
    }
    libxml_clear_errors();
}

$users = [];
$stmt = $conn->prepare("SELECT id_user, username, email, is_admin, reputazione FROM users ORDER BY id_user ASC");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $uid = (int)$row['id_user'];
        if (isset($reputations[$uid])) {
            $row['reputazione'] = $reputations[$uid];
        } else {
            $row['reputazione'] = is_numeric($row['reputazione']) ? (float)$row['reputazione'] : $row['reputazione'];
        }
        $users[] = $row;
    }
    $stmt->close();
} else {
    $_SESSION['super_status'] = 'Errore nella lettura degli utenti.';
    header('Location: superadmin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Super Admin - Gestione Utenti</title>
  <link rel="stylesheet" href="style/style_faq.css">
  <style>
    .super-container { max-width: 1100px; margin: 40px auto; padding: 20px; background:#1c1c1c; border-radius:10px; border:1px solid #333; }
    .user-table { width:100%; border-collapse: collapse; margin-top: 10px; color: #ddd; }
    .user-table th, .user-table td { padding: 10px; border-bottom: 1px solid #2b2b2b; text-align: left; }
    .user-table th { color: yellow; }
    .small-muted { font-size:0.9rem; color:#aaa; }
    .action-form { display:inline-block; margin:0; }
    .message.success { background:#132; color:#cfc; padding:8px 12px; border-radius:6px; margin-bottom:12px; display:inline-block; }
    .admin-action-btn { background:transparent; border:1px solid #444; padding:6px 8px; border-radius:6px; color:#ddd; cursor:pointer; }
    .admin-action-btn:hover { border-color:#666; }
  </style>
</head>
<body>
  <div class="super-container">
    <h2 style="color:yellow; margin-top:0;">Super Admin — Gestione privilegi</h2>

    <?php if ($status_message): ?>
      <div class="message success"><?= h($status_message) ?></div>
    <?php endif; ?>

    <p class="small-muted">Seleziona un utente per promuoverlo o revocargli i permessi di admin. L'utente <strong>root</strong> non può essere modificato da questa interfaccia.</p>

    <table class="user-table" aria-live="polite">
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Email</th>
          <th>Admin</th>
          <th>Reputazione</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <?php
            if ($u['username'] === 'root') continue;
            $isAdmin = (int)$u['is_admin'] === 1;
            $rep_display = is_numeric($u['reputazione']) ? number_format((float)$u['reputazione'], 2, ',', '') : h((string)$u['reputazione']);
          ?>
          <tr>
            <td><?= h($u['id_user']) ?></td>
            <td><?= h($u['username']) ?></td>
            <td><?= h($u['email']) ?></td>
            <td><?= $isAdmin ? 'Sì' : 'No' ?></td>
            <td><?= $rep_display ?></td>
            <td>
              <form method="post" action="promuovi_utente.php" class="action-form" onsubmit="return confirm('Confermi l\\'azione per <?= h($u['username']) ?>?');">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="user_id" value="<?= h($u['id_user']) ?>">
                <input type="hidden" name="action" value="<?= $isAdmin ? 'revoke' : 'promote' ?>">
                <button type="submit" class="admin-action-btn" title="<?= $isAdmin ? 'Revoca admin' : 'Promuovi admin' ?>">
                  <span class="btn-icon"><?= $isAdmin ? '🔽' : '🔼' ?></span>
                  <span class="btn-label"><?= $isAdmin ? 'Revoca' : 'Promuovi' ?></span>
                </button>
              </form>

              <a href="profilo_pubblico.php?id=<?= h($u['id_user']) ?>" class="admin-action-btn" style="margin-left:8px; text-decoration:none;">
                <span class="btn-icon">🔍</span><span class="btn-label">Visualizza</span>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <p style="margin-top:20px;">
      <a href="home_page.php" class="admin-btn" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
        ← Torna al sito
      </a>
    </p>
  </div>
</body>
</html>
