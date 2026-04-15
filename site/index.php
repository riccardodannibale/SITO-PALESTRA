<?php
    // Puoi aggiungere qui eventuale logica PHP se necessario
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BODY FIT</title>
    <style>
        body {
            background-color: black;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-family: Arial, sans-serif;
            text-align: center;
        }
        .loader-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: black;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .loader img {
            width: 150px;
            animation: rotateLogo 3s linear infinite;
        }
        .loader-text {
            color: yellow;
            font-size: 1em;
            margin-top: 10px;
        }
        @keyframes rotateLogo {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes fadeOut {
            0% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }
        .content {
            display: none;
            flex-direction: column;
            align-items: center;
        }
        h1 {
            font-size: 3em;
            margin-bottom: 20px;
            color: yellow;
            margin-top: -80px;
        }
        .button-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .button {
            background-color: yellow;
            color: black;
            border: none;
            padding: 15px 30px;
            font-size: 1.2em;
            cursor: pointer;
            border-radius: 15px;
            text-decoration: none;
            font-weight: bold;
            transition: transform 0.2s;
        }
        .button:hover {
            transform: scale(1.1);
        }
        .footer {
            position: absolute;
            bottom: 10px;
            font-size: 0.9em;
        }
    </style>
    <script>
        window.onload = function() {
            setTimeout(() => {
                document.querySelector('.loader-container').style.display = 'none';
                document.querySelector('.content').style.display = 'flex';
            }, 2000);
        };
    </script>
</head>
<body>
    <div class="loader-container">
        <div class="loader">
            <img src="style/img/logo.png" alt="BODY FIT Logo">
            <div class="loader-text">BODY FIT</div>
        </div>
    </div>
    <div class="content">
        <h1>BODY FIT</h1>
        <div class="button-container">
            <a href="login_cliente.php" class="button">Accesso Cliente</a>
            <a href="login_dipendente.php" class="button">Accesso Dipendente</a>
        </div>
        <div class="footer">
            &copy; <?php echo date('Y'); ?> BODY FIT. Tutti i diritti riservati.
        </div>
    </div>
</body>
</html>
