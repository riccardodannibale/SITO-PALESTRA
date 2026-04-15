<?php
session_start();

$error_message = '';
$success_message = '';

// Determina quale sezione mostrare (login oppure recupero password)
// Se viene passato il parametro GET "action=recover" allora viene mostrato il form per il recupero password,
// altrimenti (default) viene mostrato il form per il login.
$action = isset($_GET['action']) ? $_GET['action'] : 'login';

// Gestione del POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verifica quale form è stato inviato tramite il campo nascosto "action"
    $formAction = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($formAction == 'login') {
        // ----- LOGIN -----
        $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($email) || empty($password)) {
            $error_message = "Per favore, compila tutti i campi!";
        } else {
            // Parametri di connessione al database
            $servername  = "localhost";
            $username_db = "root";           // Il tuo username DB
            $password_db = "Riccardo.03";      // La tua password DB
            $dbname      = "palestra";         // Il nome del tuo DB

            // Crea la connessione
            $conn = new mysqli($servername, $username_db, $password_db, $dbname);
            if ($conn->connect_error) {
                die("Connessione fallita: " . $conn->connect_error);
            }

            // Verifica se l'email è registrata e se l'utente è un cliente (is_dipendente = 0)
            $sql = "SELECT * FROM utenti WHERE email = ? AND is_dipendente = 0";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                die("Errore nella preparazione della query: " . $conn->error);
            }
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    // Login riuscito
                    $_SESSION['logged_in'] = true;
                    $_SESSION['username']  = $user['nome'];
                    $_SESSION['email']     = $user['email'];
                    header("Location: home_cliente.php");
                    exit;
                } else {
                    $error_message = "Password errata!";
                }
            } else {
                $error_message = "Email non trovata o non autorizzata!";
            }

            $stmt->close();
            $conn->close();
        }
    } elseif ($formAction == 'recover') {
        // ----- RECUPERO PASSWORD -----
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';

        if (empty($email)) {
            $error_message = "Inserisci la tua email!";
        } else {
            // Parametri di connessione al database
            $servername  = "localhost";
            $username_db = "root";
            $password_db = "Riccardo.03";
            $dbname      = "palestra";

            // Crea la connessione
            $conn = new mysqli($servername, $username_db, $password_db, $dbname);
            if ($conn->connect_error) {
                die("Connessione fallita: " . $conn->connect_error);
            }

            // Verifica se l'email esiste in utenti e se l'utente è un cliente (is_dipendente = 0)
            $sql = "SELECT * FROM utenti WHERE email = ? AND is_dipendente = 0";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                die("Errore nella preparazione della query: " . $conn->error);
            }
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Email trovata: genera una nuova password temporanea
                $temp_password = bin2hex(random_bytes(4)); // genera 8 caratteri esadecimali
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

                // Aggiorna la password dell'utente nella tabella 'utenti'
                $update_sql = "UPDATE utenti SET password = ? WHERE email = ? AND is_dipendente = 0";
                $update_stmt = $conn->prepare($update_sql);
                if ($update_stmt === false) {
                    die("Errore nella preparazione della query: " . $conn->error);
                }
                $update_stmt->bind_param("ss", $hashed_password, $email);
                $update_stmt->execute();
                $update_stmt->close();

                // In un ambiente reale invieresti la nuova password via email all'utente.
                // Per ora la mostriamo a schermo.
                $success_message = "La tua nuova password è: <strong>$temp_password</strong><br>
                                    Ti consigliamo di cambiarla subito dopo il login.";
            } else {
                // Email non trovata o non autorizzata: reindirizza alla pagina di registrazione
                header("Location: sign_up.php");
                exit;
            }

            $stmt->close();
            $conn->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title><?php echo ($action == 'recover') ? "Recupera Password" : "Login Cliente"; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Stile preso da quello fornito precedentemente */
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
        a {
            color: yellow;
        }
    </style>
</head>
<body>
    <?php if ($action == 'recover'): ?>
        <h1>Recupera Password</h1>
        <?php
        if ($error_message) {
            echo "<p style='color:red;'>$error_message</p>";
        }
        if ($success_message) {
            echo "<p style='color:green;'>$success_message</p>";
        }
        ?>
        <form action="login_cliente.php?action=recover" method="POST">
            <!-- Campo nascosto per identificare il form -->
            <input type="hidden" name="action" value="recover">
            <label for="email">Inserisci la tua email:</label><br>
            <input type="email" name="email" id="email" required><br><br>
            <button type="submit">Recupera Password</button>
        </form>
        <br>
        <a href="login_cliente.php">Torna al login</a>
    <?php else: ?>
        <h1>Login Cliente</h1>
        <?php
        if ($error_message) {
            echo "<p style='color:red;'>$error_message</p>";
        }
        ?>
        <form action="login_cliente.php" method="POST">
            <!-- Campo nascosto per identificare il form -->
            <input type="hidden" name="action" value="login">
            <label for="email">Email:</label><br>
            <input type="email" id="email" name="email" required><br><br>
            
            <label for="password">Password:</label><br>
            <input type="password" id="password" name="password" required><br><br>
            
            <button type="submit">Accedi</button>
        </form>
        <br>
        <a href="login_cliente.php?action=recover">Hai dimenticato la password?</a>
        <br><br>
        <form action="sign_up.php" method="GET">
            <button type="submit">Non hai un account? Registrati</button>
        </form>
    <?php endif; ?>
</body>
</html>
