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

$xmlFile = 'xml/faq.xml';

if (!file_exists($xmlFile)) {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><faqs></faqs>');
    if (!is_dir(dirname($xmlFile))) {
        mkdir(dirname($xmlFile), 0755, true);
    }
    $xml->asXML($xmlFile);
}

$faqs = [];
if (file_exists($xmlFile) && filesize($xmlFile) > 0) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($xmlFile);
    libxml_use_internal_errors(false);
    if ($xml && isset($xml->faq)) {
        foreach ($xml->faq as $faq) {
            $faqs[] = [
                'id'       => (string)$faq->id,
                'question' => (string)$faq->question,
                'answer'   => (string)$faq->answer
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PALESTRW – FAQ</title>
  <link rel="stylesheet" href="style/style_faq.css" type="text/css" />
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
        <li><a href="chi_siamo.php"><img src="icon/chi_siamo.png" class="icon">Chi Siamo</a></li>
      <?php else: ?>
        <li><a href="home_page.php"><img src="icon/home.png" class="icon">Home</a></li>
        <li><a href="promo.php"><img src="icon/promo.png" class="icon">Promo</a></li>
        <li><a href="corsi.php"><img src="icon/corsi.png" class="icon">Corsi</a></li>
        <li><a href="profilo.php"><img src="icon/profilo.png" class="icon">Profilo</a></li>
        <li><a href="chi_siamo.php"><img src="icon/chi_siamo.png" class="icon">Chi Siamo</a></li>
        <li><a href="logout.php"><img src="icon/logout.png" class="icon">Logout</a></li>
      <?php endif; ?>
    </ul>
  </div>

  <main id="main-content" class="main-content faq-page">
    <section class="content-section" style="flex: 1 1 auto; max-width: 100%;">
      <h1 class="titolo">FAQ – Domande Frequenti</h1>
      <div class="faq-list" style="max-width:900px; margin: 0 auto 40px;">
        <?php foreach ($faqs as $index => $faq): ?>
          <div class="faq-item<?php echo ($index >= 3 ? ' hidden' : ''); ?>">
            <button class="faq-question"><?= htmlspecialchars($faq['question']) ?></button>
            <div class="faq-answer"><?= htmlspecialchars($faq['answer']) ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (count($faqs) > 3): ?>
          <button id="toggle-faqs" class="toggle-btn">Mostra altre FAQ</button>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($is_admin): ?>
    <section class="admin-panel" style="max-width:1000px; margin: 0 auto 40px;">
      <h3>Gestione FAQ (Admin)</h3>
      <?php if (isset($_GET['status']) && isset($_GET['message'])): ?>
        <div class="message <?= $_GET['status'] === 'success' ? 'success' : 'error' ?>">
          <?= htmlspecialchars($_GET['message']) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="aggiungi_faq.php" class="admin-form admin-add-form">
        <h4>Aggiungi Nuova FAQ</h4>
        <input type="text" name="new_question" placeholder="Nuova domanda" required>
        <textarea name="new_answer" placeholder="Risposta" required></textarea>
        <div class="admin-actions">
          <button type="submit" class="admin-btn admin-btn--add"><span class="btn-icon">➕</span>Aggiungi FAQ</button>
        </div>
      </form>

      <div class="current-faqs">
        <h4>FAQ Esistenti</h4>
        <?php if (count($faqs) > 0): ?>
          <?php foreach ($faqs as $faq): ?>
            <div class="faq-admin-item">
              <div><strong>ID:</strong> <?= htmlspecialchars($faq['id']) ?></div>
              <div><strong>Domanda:</strong> <?= htmlspecialchars($faq['question']) ?></div>
              <div><strong>Risposta:</strong> <?= htmlspecialchars($faq['answer']) ?></div>
              <div class="faq-actions">
                <button class="edit-btn admin-action-btn" title="Modifica"
                        onclick="openEditModal(
                          '<?= $faq['id'] ?>',
                          '<?= htmlspecialchars($faq['question'], ENT_QUOTES) ?>',
                          '<?= htmlspecialchars($faq['answer'], ENT_QUOTES) ?>'
                        )">
                  <span class="btn-icon">✏️</span><span class="btn-label">Modifica</span>
                </button>

                <form method="POST" action="elimina_faq.php" onsubmit="return confirm('Sei sicuro di voler eliminare questa FAQ?');" style="display:inline-block;">
                  <input type="hidden" name="delete_id" value="<?= $faq['id'] ?>">
                  <button type="submit" class="delete-btn-sm admin-action-btn" title="Elimina">
                    <span class="btn-icon">🗑️</span><span class="btn-label">Elimina</span>
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>Nessuna FAQ disponibile</p>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

  </main>

  <div id="editModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" onclick="closeEditModal()">&times;</span>
      <form method="POST" action="modifica_faq.php" class="admin-form" id="edit-form">
        <h4>Modifica FAQ</h4>
        <input type="hidden" name="edit_id" id="edit_id">
        <input type="text" name="edit_question" id="edit_question" placeholder="Domanda" required>
        <textarea name="edit_answer" id="edit_answer" placeholder="Risposta" required></textarea>
        <div class="admin-actions">
          <button type="submit" class="admin-btn admin-btn--save"><span class="btn-icon">💾</span>Salva Modifiche</button>
          <button type="button" class="admin-btn admin-btn--cancel" onclick="closeEditModal()"><span class="btn-icon">✖️</span>Annulla</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.querySelectorAll('.faq-question').forEach(btn => {
      btn.addEventListener('click', () => {
        btn.classList.toggle('active');
        const ans = btn.nextElementSibling;
        ans.style.display = ans.style.display === 'block' ? 'none' : 'block';
      });
    });

    const toggleBtn = document.getElementById('toggle-faqs');
    if (toggleBtn) {
      toggleBtn.addEventListener('click', () => {
        document.querySelectorAll('.faq-item').forEach((item, idx) => {
          if (idx >= 3) {
            item.classList.toggle('hidden');
          }
        });
        toggleBtn.textContent = toggleBtn.textContent === 'Mostra altre FAQ'
          ? 'Mostra meno FAQ'
          : 'Mostra altre FAQ';
      });
    }

    function openEditModal(id, question, answer) {
      document.getElementById('edit_id').value = id;
      document.getElementById('edit_question').value = question;
      document.getElementById('edit_answer').value = answer;
      
      const modal = document.getElementById('editModal');
      modal.style.display = 'flex';
    }

    function closeEditModal() {
      document.getElementById('editModal').style.display = 'none';
    }

    window.addEventListener('click', (event) => {
      const modal = document.getElementById('editModal');
      if (event.target === modal) {
        closeEditModal();
      }
    });
  </script>

  <div id="footer">
    <p>&copy; <?= date('Y') ?> PALESTRW. Tutti i diritti riservati.</p>
  </div>
</body>
</html>
