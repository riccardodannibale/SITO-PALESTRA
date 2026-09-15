<?php
session_start();

$id_user  = $_SESSION['id_user'] ?? null;
$is_admin = false;
if ($id_user) {
    include __DIR__ . '/db.php';
    if (isset($conn)) {
        $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id_user = ?");
        $stmt->bind_param("i", $id_user);
        $stmt->execute();
        $stmt->bind_result($flag_admin);
        $stmt->fetch();
        $stmt->close();
        $is_admin = ($flag_admin == 1);
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PALESTRW – Chi Siamo</title>
  <link rel="stylesheet" href="style/style_Chi_siamo.css" type="text/css" />
</head>
<body>
  <div id="header" class="main-header">
    <div class="logo">PALESTRW</div>
    <ul class="main-menu">
      <?php if (!$id_user): ?>
        <li><a href="login.php"><img src="icon/area_riservata.png" class="icon">Area Riservata</a></li>
        <li><a href="home_page.php"><img src="icon/home.png" class="icon">Home</a></li>
        <li><a href="promo.php"><img src="icon/promo.png" class="icon">Promo</a></li>
        <li><a href="corsi.php"><img src="icon/corsi.png" class="icon">Corsi</a></li>
        <li><a href="faq.php"><img src="icon/faq.png" class="icon">FAQ</a></li>
      <?php else: ?>
        <li><a href="home_page.php"><img src="icon/home.png" class="icon">Home</a></li>
        <li><a href="promo.php"><img src="icon/promo.png" class="icon">Promo</a></li>
        <li><a href="corsi.php"><img src="icon/corsi.png" class="icon">Corsi</a></li>
        <li><a href="profilo.php"><img src="icon/profilo.png" class="icon">Profilo</a></li>
        <li><a href="faq.php"><img src="icon/faq.png" class="icon">FAQ</a></li>
        <li><a href="logout.php"><img src="icon/logout.png" class="icon">Logout</a></li>
      <?php endif; ?>
    </ul>
  </div>

  <main id="main-content" class="main-content">
    <section class="content-section">
      <h1 class="titolo">CHI SIAMO</h1>
      <ol class="lista">
        <li><strong>INFO</strong></li>
        <li>Dove: Via Alessio Rossi 14, Latina</li>
        <li>Contatto: +39 365 564 8300</li>
      </ol>
      <div class="container">
        <span class="clickable-indicator">Clicca sulla mappa per aprirla</span>
        <a href="https://www.google.it/maps/place/Via+Alessio+Rossi+14,+Latina"
           target="_blank"
           rel="noopener noreferrer">
          <img src="img/mappa.png" alt="Mappa della sede di PALESTRW" />
        </a>
      </div>
    </section>
  </main>

  <div id="footer">
    <p>&copy; <?= date('Y') ?> PALESTRW. Tutti i diritti riservati.</p>
  </div>
</body>
</html>
