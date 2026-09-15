<?php
// Avvia la sessione PHP per poter gestire dati persistenti dell'utente 
session_start();
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="it">
<head>
  <!-- Imposta la codifica dei caratteri -->
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  
  <!-- Titolo della pagina che appare sulla scheda del browser -->
  <title>FITNESS STUDIO</title>
  
  <!-- Collegamento al foglio di stile CSS per definire l'aspetto grafico -->
  <link rel="stylesheet" href="style/Style_home_page.css" type="text/css" />
</head>

<body>
  <!-- Intestazione della pagina, contenente logo e menu di navigazione -->
  <div id="header" class="main-header">
    <div class="logo">FITNESS STUDIO</div>

    <!-- Menu principale -->
    <ul class="main-menu">
      <?php if (!isset($_SESSION['id_user'])): ?>
        <!-- Se l'utente NON è loggato, mostra link per login -->
        <li><a href="login.php">Area Riservata</a></li>
      <?php else: ?>
        <!-- Se l'utente è loggato, mostra link aggiuntivi -->
        <li><a href="stato_abbonamento.php">Stato abbonamento</a></li>
        <li><a href="logout.php">Logout</a></li>
      <?php endif; ?>

      <!-- Questi link sono sempre visibili -->
      <li><a href="promo.php">Promo</a></li>
      <li><a href="corsi.php">Corsi</a></li>
      <li><a href="Chi_Siamo.php">Chi Siamo</a></li>
    </ul>
  </div>

  <!-- Corpo centrale della pagina -->
  <div id="main-content" class="main-content">
    <h1 class="welcome">Benvenuti in Fitness Studio</h1>

    <!-- Tabella con 4 immagini disposte in griglia 2x2 -->
    <table class="tabella">
      <tr>
        <td><img src="img/1-quadrante.png" alt="Quadrante 1" /></td>
        <td><img src="img/2-quadrante.png" alt="Quadrante 2" /></td>
      </tr>
      <tr>
        <td><img src="img/3-quadrante.png" alt="Quadrante 3" /></td>
        <td><img src="img/4-quadrante.png" alt="Quadrante 4" /></td>
      </tr>
    </table>
  </div>

  <!-- Piè di pagina -->
  <div id="footer">
    <p>&copy; 2025 FITNESS STUDIO. Tutti i diritti riservati.</p>
  </div>
</body>
</html>
