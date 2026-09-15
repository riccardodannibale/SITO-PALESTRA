<?php
session_start();
require_once __DIR__ . '/db.php';

$error = '';
$temp_password = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Inserisci una email valida.';
    } else {
        $stmt = $conn->prepare('SELECT id_user FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id_user);
            $stmt->fetch();

            $temp_password = substr(bin2hex(random_bytes(6)), 0, 10);
            $new_hash = password_hash($temp_password, PASSWORD_DEFAULT);

            $upd = $conn->prepare('UPDATE users SET password = ?, password_temp = 1, force_pw_change = 1 WHERE id_user = ?');
            $upd->bind_param('si', $new_hash, $id_user);
            if (!$upd->execute()) {
                $error = 'Errore durante l’aggiornamento.';
            }
            $upd->close();
        } else {
            $error = 'Email non trovata.';
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Recupero Password - Fitness Studio</title>
  <link rel="stylesheet" href="style/style_login.css" />
</head>
<body>
  <div class="form-container">
    <h2>Recupero Password</h2>

    <?php if ($error): ?>
      <p class="error"><?= htmlspecialchars($error, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($temp_password): ?>
      <p class="success">
        La tua password temporanea è: <strong><?= htmlspecialchars($temp_password, ENT_QUOTES, 'UTF-8') ?></strong>
      </p>
      <p>Effettua il login e cambia subito la password.</p>
      <p><a href="login.php">Vai al login</a></p>
    <?php else: ?>
      <form method="post" novalidate>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus />
        <button type="submit">Genera Password Temporanea</button>
      </form>
      <p><a href="login.php">Torna al login</a></p>
    <?php endif; ?>
  </div>
</body>
</html>
