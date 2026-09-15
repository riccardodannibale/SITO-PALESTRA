<?php
// Avvio della sessione per poter usare le variabili di sessione
session_start();
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" /> <!-- Settaggio charset UTF-8 per caratteri speciali -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0" /> <!-- Responsive design -->
  <title>FITNESS STUDIO</title>
  <!-- Collegamento al foglio di stile esterno -->
  <link rel="stylesheet" href="style/Chi_Siamo.css" type="text/css" />
</head>

<body>
  <!-- Intestazione principale del sito -->
  <header id="header" class="main-header">
    <div class="logo">FITNESS STUDIO</div>

    <!-- Menu di navigazione -->
    <nav>
      <ul class="main-menu">
        <?php if (!isset($_SESSION['id_user'])): ?>
          <!-- Link visibile solo se l'utente NON è loggato -->
          <li><a href="login.php">Area Riservata</a></li>
        <?php else: ?>
          <!-- Link visibili solo se l'utente è loggato -->
          <li><a href="stato_abbonamento.php">Stato abbonamento</a></li>
          <li><a href="logout.php">Logout</a></li>
        <?php endif; ?>

        <!-- Link sempre visibili -->
        <li><a href="promo.php">Promo</a></li>
        <li><a href="corsi.php">Corsi</a></li>
        <li><a href="Chi_Siamo.php" class="active">Chi Siamo</a></li>
      </ul>
    </nav>
  </header>

  <!-- Contenuto principale della pagina -->
  <main id="main-content" class="main-content">
    <h1 class="titolo">CHI SIAMO</h1>

    <!-- Informazioni di contatto -->
    <ol class="lista">
      <li><strong>INFO</strong></li>
      <li>Dove: Via Alessio Rossi 14, Latina</li>
      <li>Contatto: +39 365 564 8300</li>
    </ol>

    <!-- Sezione con mappa interattiva -->
    <div class="container">
      <span class="clickable-indicator">Clicca sulla mappa per aprirla</span>
      <a href="https://www.google.it/maps/place/Via+Alessio+Rossi+14,+Latina" target="_blank" rel="noopener noreferrer">
        <!-- Immagine della mappa con attributo alt descrittivo -->
        <img src="img/mappa.png" alt="Mappa della sede di FITNESS STUDIO" />
      </a>
    </div>
  </main>

  <!-- Footer con diritti -->
  <footer id="footer">
    <p>&copy; 2025 FITNESS STUDIO. Tutti i diritti riservati.</p>
  </footer>
</body>
</html>
