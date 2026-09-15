<?php
// Avvia la sessione per poter utilizzare variabili di sessione
session_start();

// Include il file di connessione al database
include 'db.php';

// Variabile per memorizzare eventuali messaggi di errore
$error = '';

// Controlla se la richiesta HTTP è di tipo POST (invio modulo)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Recupera i dati inviati dal form
    $username = $_POST["username"];
    $email = $_POST["email"];
    $password = $_POST["password"]; // NOTA: password non è hashata, quindi salvata in chiaro

    // Prepara una query per verificare se l'email è già presente nel database
    $check = $conn->prepare("SELECT id_user FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    // Se l'email è già registrata, imposta un messaggio di errore
    if ($check->num_rows > 0) {
        $error = "Email già registrata.";
    } else {
        // Altrimenti, prepara la query per inserire il nuovo utente
        $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $email, $password);

        // Esegue la query di inserimento
        if ($stmt->execute()) {
            // Se l'inserimento ha successo, salva id e username in sessione
            $_SESSION["id_user"] = $stmt->insert_id;
            $_SESSION["username"] = $username;

            // Reindirizza l'utente alla home page dopo la registrazione
            header("Location: home_page.php");
            exit;
        } else {
            // Se l'inserimento fallisce, mostra un messaggio di errore generico
            $error = "Errore nella registrazione.";
        }
        // Chiude lo statement di inserimento
        $stmt->close();
    }
    // Chiude lo statement di controllo duplicati
    $check->close();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <!-- Specifica la codifica dei caratteri della pagina -->
    <meta charset="UTF-8" />
    <title>Registrazione</title>
    <!-- Collegamento al file CSS per lo stile -->
    <link rel="stylesheet" href="style/style.css" />
</head>
<body>
<div class="form-container">
    <h2>Registrati</h2>

    <!-- Se c'è un errore, lo mostra in modo sicuro per evitare XSS -->
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <!-- Form di registrazione con input obbligatori -->
    <form method="POST">
        <label>Username</label>
        <input type="text" name="username" required />

        <label>Email</label>
        <input type="email" name="email" required />

        <label>Password</label>
        <input type="password" name="password" required />

        <button type="submit">Registrati</button>
    </form>
</div>
</body>
</html>
