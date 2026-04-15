<?php
session_start();
$error_message = '';

// Gestione del login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recupera i dati del login
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Connessione al database
    $servername = "localhost";
    $username = "root";  // Il tuo username DB
    $password_db = "Riccardo.03";    // La tua password DB
    $dbname = "palestra";  // Il nome del tuo DB

    $conn = new mysqli($servername, $username, $password_db, $dbname);
    if ($conn->connect_error) {
        die("Connessione fallita: " . $conn->connect_error);
    }

    // Verifica se l'email è registrata come dipendente
    $sql = "SELECT * FROM utenti WHERE email = ? AND is_dipendente = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Login riuscito
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $user['nome'];
            $_SESSION['email'] = $user['email'];
            header("Location: home_dipendente.php");
            exit;
        } else {
            $error_message = "Password errata!";
        }
    } else {
        $error_message = "Dipendente non trovato!";
    }

    // Chiudi la connessione
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Dipendente</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            flex-direction: column;
            text-align: center;
            font-family: Arial, sans-serif;
            background-color: black;
            color: white;
        }
        form {
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(255, 255, 0, 0.5);
            width: 300px;
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
        input {
            width: 90%;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            border: none;
        }
        p {
            color: red;
        }
    </style>
</head>
<body>
    <h1>Login Dipendente</h1>

    <?php if ($error_message) { echo "<p>$error_message</p>"; } ?>

    <form action="login_dipendente.php" method="POST">
        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" required><br><br>

        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required><br><br>

        <button type="submit">Accedi</button>
    </form>
</body>
</html>
