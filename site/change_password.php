<?php
session_start();
include('db.php');

// Verifica se l'utente è loggato
if (!isset($_SESSION['email'])) {
    header('Location: login_cliente.php');
    exit;
}

$email = $_SESSION['email'];
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Controlla che tutti i campi siano compilati
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Compila tutti i campi.";
    } elseif ($new_password !== $confirm_password) {
        $error = "La nuova password e la conferma non coincidono.";
    } else {
        // Recupera la password attuale dal database per l'utente loggato
        $sql = "SELECT password FROM utenti WHERE email = ? AND is_dipendente = 0";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Errore nella preparazione della query: " . $conn->error);
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows == 0) {
            $error = "Utente non trovato.";
        } else {
            $row = $result->fetch_assoc();
            if (!password_verify($old_password, $row['password'])) {
                $error = "La password attuale non è corretta.";
            } else {
                // Se la password attuale è corretta, aggiorna con la nuova password
                $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE utenti SET password = ? WHERE email = ? AND is_dipendente = 0";
                $update_stmt = $conn->prepare($update_sql);
                if (!$update_stmt) {
                    die("Errore nella preparazione della query: " . $conn->error);
                }
                $update_stmt->bind_param("ss", $new_hashed, $email);
                if ($update_stmt->execute()) {
                    $success = "Password aggiornata correttamente.";
                } else {
                    $error = "Errore durante l'aggiornamento della password.";
                }
                $update_stmt->close();
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambia Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: black;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .form-container {
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(255, 255, 0, 0.5);
            width: 300px;
            text-align: center;
        }
        input[type="password"] {
            width: 90%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: none;
        }
        button {
            background-color: yellow;
            color: black;
            border: none;
            padding: 10px 20px;
            font-size: 1em;
            cursor: pointer;
            border-radius: 10px;
            margin-top: 10px;
        }
        button:hover {
            transform: scale(1.1);
        }
        a {
            color: yellow;
        }
        .message {
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Cambia Password</h1>
        <?php if($error): ?>
            <p class="message" style="color:red;"><?php echo $error; ?></p>
        <?php endif; ?>
        <?php if($success): ?>
            <p class="message" style="color:green;"><?php echo $success; ?></p>
        <?php endif; ?>
        <form method="POST" action="change_password.php">
            <input type="password" name="old_password" placeholder="Password Attuale" required><br>
            <input type="password" name="new_password" placeholder="Nuova Password" required><br>
            <input type="password" name="confirm_password" placeholder="Conferma Nuova Password" required><br>
            <button type="submit">Aggiorna Password</button>
        </form>
        <br>
        <a href="profilo_cliente.php">Torna al Profilo</a>
    </div>
</body>
</html>
