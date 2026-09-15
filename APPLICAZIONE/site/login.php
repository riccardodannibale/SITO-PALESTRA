<?php
session_start();
require_once __DIR__ . '/db.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Richiesta non valida.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = trim($_POST['password']   ?? '');

        if ($identifier !== '' && $password !== '') {
            $stmt = $conn->prepare("
                SELECT id_user
                     , username
                     , password
                     , is_admin
                     , password_temp
                     , force_pw_change
                  FROM users
                 WHERE email = ? OR username = ?
                 LIMIT 1
            ");
            $stmt->bind_param("ss", $identifier, $identifier);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                if (password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['id_user']         = (int)$user['id_user'];
                    $_SESSION['username']        = $user['username'];
                    $_SESSION['role']            = $user['is_admin'] ? 'admin' : 'user';
                    $_SESSION['is_admin']        = (int)$user['is_admin'];
                    $_SESSION['force_pw_change'] = (bool)$user['force_pw_change'];

                    if ($user['username'] === 'root' && (int)$user['is_admin'] === 1) {
                        header('Location: superadmin.php');
                        exit;
                    }

                    if ($user['password_temp'] || $user['force_pw_change']) {
                        header('Location: profilo.php');
                    } else {
                        header('Location: home_page.php');
                    }
                    exit;
                }
            }
            $stmt->close();
        }

        $error = 'Credenziali non valide.';
    }
    $_SESSION['login_error'] = $error;
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Login - Fitness Studio</title>
  <link rel="stylesheet" href="style/style_login.css">
</head>
<body>
  <div class="form-container">
    <h2>Login</h2>
    <?php if ($error): ?>
      <p class="error"><?= h($error) ?></p>
    <?php endif; ?>
    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
      <label for="identifier">Email o Username</label>
      <input type="text" id="identifier" name="identifier" required autofocus>
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
      <button type="submit">Accedi</button>
    </form>
    <p><a href="password_dimenticata.php">Password dimenticata?</a></p>
    <p>Non hai un account? <a href="register.php">Registrati</a></p>
  </div>
</body>
</html>
