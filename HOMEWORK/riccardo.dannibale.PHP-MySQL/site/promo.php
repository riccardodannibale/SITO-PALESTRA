<?php
// Avvia la sessione per poter accedere alle variabili di sessione, come l'id utente
session_start();
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<!-- Definisce il tipo di documento e la versione XHTML usata -->
<html xmlns="http://www.w3.org/1999/xhtml" lang="it">
<head>
  <!-- Specifica la codifica dei caratteri usata nella pagina -->
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <!-- Titolo della pagina visualizzato nella scheda del browser -->
  <title>FITNESS STUDIO</title>
  <!-- Collegamento al file CSS per la formattazione della pagina -->
  <link rel="stylesheet" href="style/promo.css" type="text/css" />
</head>

<body>
  <!-- Intestazione principale della pagina -->
  <div id="header" class="main-header">
    <!-- Logo o nome del sito -->
    <div class="logo">FITNESS STUDIO</div>

    <!-- Menu di navigazione principale -->
    <ul class="main-menu">
      <?php if (!isset($_SESSION['id_user'])): ?>
        <!-- Se l'utente non è loggato mostra il link all'area riservata (login) -->
        <li><a href="login.php">Area Riservata</a></li>
      <?php else: ?>
        <!-- Se l'utente è loggato mostra link per stato abbonamento e logout -->
        <li><a href="stato_abbonamento.php">Stato abbonamento</a></li>
        <li><a href="logout.php">Logout</a></li>
      <?php endif; ?>
      <!-- Link statici alle altre pagine -->
      <li><a href="promo.php">Promo</a></li>
      <li><a href="corsi.php">Corsi</a></li>
      <li><a href="Chi_Siamo.php">Chi Siamo</a></li>
    </ul>
  </div>

  <!-- Titolo principale della pagina -->
  <h1 class="titolo">PROMO</h1>

  <!-- Box di avviso/promozione con attenzione e testo -->
  <div class="alert" role="alert" aria-live="polite">
    <p class="attenzione">ATTENZIONE</p>
    <p class="alert-text">Se ti abboni entro il 01/05/2025, per te un asciugamano in omaggio</p>
    <!-- Icona decorativa per l'alert -->
    <img src="img/allarme.png" alt="Icona allarme promozione" class="alert-img" />
  </div>

  <!-- Tabella riepilogativa delle tipologie di abbonamento -->
  <table class="tabella" summary="Tipologie di abbonamento, descrizione e prezzi">
    <thead>
      <tr class="corso">
        <th scope="col">Corsi</th>
        <th scope="col">Sala Pesi</th>
        <th scope="col">Completo</th>
      </tr>
    </thead>
    <tbody>
      <tr class="descrizione">
        <!-- Descrizione abbonamento Corsi -->
        <td><p>Con l'abbonamento di tipo "CORSI" puoi usufruire dei vari corsi che mettiamo a disposizione durante la settimana.</p></td>
        <!-- Descrizione abbonamento Sala Pesi -->
        <td><p>Con l'abbonamento di tipo "SALA PESI" puoi usufruire dei vantaggi della nostra sala composta da varie zone per un allenamento completo.</p></td>
        <!-- Descrizione abbonamento Completo (corsi + sala pesi) -->
        <td><p>Con l'abbonamento di tipo "COMPLETO" puoi ottenere con un solo abbonamento i vantaggi di entrambe le tipologie "CORSI + SALA PESI".</p></td>
      </tr>
      <tr class="prezzi">
        <!-- Prezzi per i vari abbonamenti -->
        <td><p>300 &euro;</p></td>
        <td><p>240 &euro;</p></td>
        <td><p>440 &euro;</p></td>
      </tr>
    </tbody>
  </table>

  <!-- Contenitore immagini promozionali -->
  <div class="container">
    <img src="img/promo1.png" alt="Immagine promozionale 1" />
    <img src="img/promo2.png" alt="Immagine promozionale 2" />
    <img src="img/promo3.png" alt="Immagine promozionale 3" />
  </div>

  <!-- Footer con copyright -->
  <div id="footer">
    <p>&copy; 2025 FITNESS STUDIO. Tutti i diritti riservati.</p>
  </div>
</body>
</html>
