<?php
session_start();
$error_message = '';

// Gestione della registrazione
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recupera i dati del form
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Connessione al database
    $servername = "localhost";
    $username = "root";  // Il tuo username DB
    $password_db = "Riccardo.03";  // La tua password DB
    $dbname = "palestra";  // Il nome del tuo DB

    $conn = new mysqli($servername, $username, $password_db, $dbname);
    if ($conn->connect_error) {
        die("Connessione fallita: " . $conn->connect_error);
    }

    // Verifica se l'email è già registrata
    $sql = "SELECT * FROM utenti WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $error_message = "Email già registrata!";
    } else {
        // Hash della password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Inserimento nel database
        $sql = "INSERT INTO utenti (nome, email, password, is_dipendente) VALUES (?, ?, ?, false)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("sss", $nome, $email, $password_hash);

            if ($stmt->execute()) {
                echo "<p>Registrazione completata! Ora puoi effettuare il login.</p>";
                header("Location: login_cliente.php");
                exit;
            } else {
                $error_message = "Errore durante la registrazione: " . $conn->error;
            }

            // Chiudi il prepared statement
            $stmt->close();
        } else {
            $error_message = "Errore nella preparazione della query: " . $conn->error;
        }
    }

    // Chiudi la connessione
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione Cliente</title>
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
    <h1>Registrazione Cliente</h1>

    <?php if ($error_message) { echo "<p>$error_message</p>"; } ?>

    <form action="sign_up.php" method="POST">
        <label for="nome">Nome:</label><br>
        <input type="text" id="nome" name="nome" required><br><br>

        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" required><br><br>

        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required><br><br>

        <button type="submit">Registrati</button>
    </form>

    <br>
    <form action="login_cliente.php" method="GET">
        <button type="submit">Hai già un account? Accedi</button>
    </form>
</body>
</html>
