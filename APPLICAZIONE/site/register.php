<?php
session_start();
require_once __DIR__ . '/db.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$username = $email = $indirizzo = $telefono = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']       ?? '';
    $indirizzo = trim($_POST['indirizzo'] ?? '');
    $telefono  = trim($_POST['telefono']  ?? '');

    if ($username === '' || $email === '' || $password === '' || $indirizzo === '' || $telefono === '') {
        $error = "Tutti i campi sono obbligatori.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email non valida.";
    } 
    elseif (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) || 
        !preg_match('/\d/', $password)       
    ) {
        $error = "La password deve contenere almeno 8 caratteri, una lettera maiuscola e un numero.";
    }
    else {
        $check = $conn->prepare("SELECT id_user FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $error = "Email già registrata.";
        } else {
            $check2 = $conn->prepare("SELECT id_user FROM users WHERE username = ?");
            $check2->bind_param("s", $username);
            $check2->execute();
            $check2->store_result();
            if ($check2->num_rows > 0) {
                $error = "Username già in uso.";
            } else {
                $check3 = $conn->prepare("SELECT id_user FROM users WHERE telefono = ?");
                $check3->bind_param("s", $telefono);
                $check3->execute();
                $check3->store_result();
                if ($check3->num_rows > 0) {
                    $error = "Numero di telefono già registrato.";
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $reputazione = 0;
                    $stmt = $conn->prepare("
                        INSERT INTO users (username, email, password, indirizzo, telefono, reputazione)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("sssssi", $username, $email, $password_hash, $indirizzo, $telefono, $reputazione);
                    if ($stmt->execute()) {
                        session_regenerate_id(true);
                        $_SESSION["id_user"]  = $stmt->insert_id;
                        $_SESSION["username"] = $username;
                        header("Location: home_page.php");
                        exit;
                    } else {
                        $error = "Errore nella registrazione. Riprova più tardi.";
                    }
                    $stmt->close();
                }
                $check3->close();
            }
            $check2->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <title>Registrazione - Fitness Studio</title>
  <link rel="stylesheet" href="style/style_login.css" />
</head>
<body>
  <div class="form-container">
    <h2>Registrati</h2>
    <?php if ($error): ?>
      <p class="error"><?= h($error) ?></p>
    <?php endif; ?>
    <form method="post" novalidate>
      <label for="username">Username</label>
      <input type="text" id="username" name="username" value="<?= h($username) ?>" required />

      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= h($email) ?>" required />

      <label for="password">Password</label>
      <input type="password" id="password" name="password" required
             pattern="(?=.*[A-Z])(?=.*\d).{8,}"
             title="Minimo 8 caratteri, almeno una maiuscola e un numero" />

      <label for="indirizzo">Indirizzo</label>
      <input type="text" id="indirizzo" name="indirizzo" value="<?= h($indirizzo) ?>" required />

      <label for="telefono">Telefono</label>
      <input type="text" id="telefono" name="telefono" value="<?= h($telefono) ?>" required />

      <button type="submit">Registrati</button>
    </form>
    <p>Hai già un account? <a href="login.php">Accedi</a></p>
  </div>
</body>
</html>
