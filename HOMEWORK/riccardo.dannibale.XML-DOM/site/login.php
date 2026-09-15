<?php
// Avvia la sessione per mantenere dati persistenti dell'utente durante la navigazione
session_start();

// Include la connessione al database definita in 'db.php'
include 'db.php';

// Inizializza la variabile per memorizzare eventuali messaggi di errore
$error = '';

// Controlla se il form è stato inviato tramite POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Recupera i dati inviati dall'utente
    $email = $_POST["email"];
    $password = $_POST["password"];

    // Prepara la query per selezionare id, username, password e ruolo admin dal database
    $stmt = $conn->prepare("
        SELECT id_user, username, password, is_admin
        FROM users
        WHERE email = ?
    ");
    // Associa il parametro email alla query
    $stmt->bind_param("s", $email);
    // Esegue la query
    $stmt->execute();
    // Memorizza il risultato per verificarne la presenza
    $stmt->store_result();

    // Se esiste esattamente un utente con quella email
    if ($stmt->num_rows === 1) {
        // Associa i risultati a variabili PHP
        $stmt->bind_result($id_user, $username, $db_password, $is_admin);
        $stmt->fetch();

        // **ATTENZIONE**: qui la password è confrontata in chiaro (per test),
        // in produzione va usata password_verify con hash sicuri
        if ($password === $db_password) {
            // Salva le informazioni essenziali dell'utente nella sessione
            $_SESSION["id_user"]   = $id_user;
            $_SESSION["username"]  = $username;
            $_SESSION["is_admin"]  = (int)$is_admin; // Cast a intero per sicurezza

            // Reindirizza alla home page dopo il login
            header("Location: home_page.php");
            exit;
        } else {
            // Password sbagliata: prepara il messaggio di errore
            $error = "Password errata.";
        }
    } else {
        // Email non trovata nel database: prepara il messaggio di errore
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

        <!-- Mostra messaggio di errore se presente -->
        <?php if (!empty($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <!-- Form per l'inserimento di email e password -->
        <form method="POST">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" required />

            <label for="password">Password</label>
            <input type="password" name="password" id="password" required />

            <button type="submit">Accedi</button>
        </form>

        <!-- Link alla pagina di registrazione per nuovi utenti -->
        <p>Non hai un account? <a href="register.php">Registrati</a></p>
    </div>
</body>
</html>
