<?php
// Avvia la sessione per gestire dati utente persistenti
session_start();

// Includi il file di connessione al database ($conn)
include 'db.php';

// Variabile per memorizzare eventuali messaggi di errore
$error = '';

// Controlla se il form è stato inviato tramite POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Prendi i dati inseriti dall'utente nel form
    $email = $_POST["email"];
    $password = $_POST["password"];

    // Prepara la query per cercare utente con l'email specificata
    $stmt = $conn->prepare("SELECT id_user, username, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email); // lega il parametro email (stringa)
    $stmt->execute();
    $stmt->store_result(); // serve per poter verificare num_rows

    // Se esiste un utente con quella email
    if ($stmt->num_rows === 1) {
        // Recupera i dati di quella riga
        $stmt->bind_result($id_user, $username, $db_password);
        $stmt->fetch();

        // Controlla se la password inserita corrisponde a quella salvata (in chiaro)
        if ($password === $db_password) {
            // Salva informazioni utente nella sessione (login riuscito)
            $_SESSION["id_user"] = $id_user;
            $_SESSION["username"] = $username;

            // Reindirizza alla pagina home
            header("Location: home_page.php");
            exit;
        } else {
            // Password sbagliata
            $error = "Password errata.";
        }
    } else {
        // Email non trovata nel database
        $error = "Email non trovata.";
    }

    // Chiude lo statement
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <title>Login - Fitness Studio</title>
    <link rel="stylesheet" href="style/style.css" />
</head>
<body>
    <div class="form-container">
        <h2>Login</h2>

        <!-- Mostra errore se presente -->
        <?php if (!empty($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <!-- Form per il login -->
        <form method="POST">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" required />

            <label for="password">Password</label>
            <input type="password" name="password" id="password" required />

            <button type="submit">Accedi</button>
        </form>

        <p>Non hai un account? <a href="register.php">Registrati</a></p>
    </div>
</body>
</html>
