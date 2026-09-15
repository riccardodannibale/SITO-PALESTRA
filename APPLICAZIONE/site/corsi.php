<?php
session_start();

date_default_timezone_set('Europe/Rome');

$id_user  = isset($_SESSION['id_user']) ? (int)$_SESSION['id_user'] : null;
$is_admin = false;
$has_active_abbonamento = false;
$id_user_abbonamento_attivo = '';

if ($id_user) {
    $dbPath = __DIR__ . '/db.php';
    if (file_exists($dbPath)) include $dbPath;
    if (isset($conn) && $conn) {
        $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id_user = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id_user);
            $stmt->execute();
            $stmt->bind_result($flag_admin);
            $stmt->fetch();
            $stmt->close();
            $is_admin = ($flag_admin == 1);
            if ($is_admin) {
                $has_active_abbonamento = false;
            }
        } else {
            error_log("Errore prepare DB: " . $conn->error);
        }
    } else {
        error_log("Connessione DB non trovata in db.php");
    }
}

function getXML(string $path): ?SimpleXMLElement {
    if (!file_exists($path)) {
        error_log("File XML non trovato: $path");
        return null;
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($path);
    if ($xml === false) {
        $errors = libxml_get_errors();
        foreach ($errors as $error) {
            error_log("Errore parsing XML ($path): " . trim($error->message));
        }
        libxml_clear_errors();
        return null;
    }

    return $xml;
}

function validateXMLWithDTD(string $path): bool {
    if (!file_exists($path)) {
        error_log("validateXMLWithDTD: file non trovato: $path");
        return false;
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    if (@$dom->load($path) === false) {
        foreach (libxml_get_errors() as $err) {
            error_log("DOM load error ($path): " . trim($err->message));
        }
        libxml_clear_errors();
        return false;
    }

    if (!$dom->doctype) {
        return true;
    }

    $valid = false;
    if (@$dom->validate()) {
        $valid = true;
    } else {
        foreach (libxml_get_errors() as $err) {
            error_log("DTD validation error ($path): " . trim($err->message));
        }
        libxml_clear_errors();
        $valid = false;
    }
    return $valid;
}

function getAttr(SimpleXMLElement $node, string $attrName, $default = '') {
    return isset($node[$attrName]) ? (string)$node[$attrName] : $default;
}

function parseXmlDate(string $dateStr) {
    $dateStr = trim($dateStr);
    if ($dateStr === '') return false;

    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i',
        'Y-m-d' 
    ];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $dateStr);
        if ($dt instanceof DateTime) return $dt;
    }

    $ts = strtotime($dateStr);
    if ($ts === false) return false;
    return (new DateTime())->setTimestamp($ts);
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


if ($id_user && !$is_admin) {
    $userAbbPath = __DIR__ . '/xml/user_abbonamenti.xml';
    $userAbbXML = getXML($userAbbPath);
    if ($userAbbXML) {
        validateXMLWithDTD($userAbbPath);

        $today = new DateTime('now', new DateTimeZone('Europe/Rome'));
        foreach ($userAbbXML->abbonamento_utente as $abb) {
            $abb_id = getAttr($abb, 'id', '');
            if ($abb_id === '') continue; 

            $abb_user_id = isset($abb->id_user) ? (int)$abb->id_user : 0;
            $stato = isset($abb->stato) ? (string)$abb->stato : '';
            $data_inizio_str = isset($abb->data_inizio) ? (string)$abb->data_inizio : '';
            $data_scadenza_str = isset($abb->data_scadenza) ? (string)$abb->data_scadenza : '';

            $data_inizio = parseXmlDate($data_inizio_str);
            $data_scadenza = parseXmlDate($data_scadenza_str);

            if (!$data_inizio || !$data_scadenza) {
                error_log("Abbonamento {$abb_id} ha date non valide: inizio='{$data_inizio_str}' scadenza='{$data_scadenza_str}'");
                continue;
            }

            if ($abb_user_id === $id_user
                && $stato === 'attivo'
                && $today >= $data_inizio
                && $today <= $data_scadenza) {
                $has_active_abbonamento = true;
                $id_user_abbonamento_attivo = $abb_id;
                break;
            }
        }
    }
}

$corsiBaseXMLPath = __DIR__ . '/xml/corsi_base.xml';
$corsiBaseXML = getXML($corsiBaseXMLPath);
$corsiBaseMap = [];
if ($corsiBaseXML) {
    validateXMLWithDTD($corsiBaseXMLPath);

    foreach ($corsiBaseXML->corso_base as $corsoBase) {
        $idAttr = getAttr($corsoBase, 'id', '');
        if ($idAttr === '') {
            error_log("corso_base senza attributo id, skipping");
            continue;
        }
        $id = (int)$idAttr;
        if ($id <= 0) {
            error_log("corso_base con id non valido ($idAttr), skipping");
            continue;
        }

        $corsiBaseMap[$id] = [
            'id' => $id,
            'nome' => isset($corsoBase->nome) ? (string)$corsoBase->nome : '',
            'descrizione' => isset($corsoBase->descrizione) ? (string)$corsoBase->descrizione : '',
            'difficolta' => isset($corsoBase->difficolta) ? (int)$corsoBase->difficolta : 1
        ];
    }
}

$lezioniXMLPath = __DIR__ . '/xml/lezioni.xml';
$lezioniXML = getXML($lezioniXMLPath);
$now_ts = time();
$lezioni = [];     
$allLezioni = [];   
$corsiMap = [];

if ($lezioniXML) {
    validateXMLWithDTD($lezioniXMLPath);

    foreach ($lezioniXML->lezione as $lezione) {
        $idAttr = getAttr($lezione, 'id', '');
        if ($idAttr === '') {
            error_log("Lezione senza attributo id, skipping");
            continue;
        }
        $id = (int)$idAttr;
        if ($id <= 0) {
            error_log("Lezione con id non valido ($idAttr), skipping");
            continue;
        }

        $id_corso_base = isset($lezione->id_corso_base) ? (int)$lezione->id_corso_base : 0;
        $corsoBase = $corsiBaseMap[$id_corso_base] ?? null;

        if (!$corsoBase) {
            error_log("Lezione id={$id} fa riferimento a id_corso_base={$id_corso_base} non presente; skipping");
            continue;
        }

        $dt_str = isset($lezione->datetime_lezione) ? (string)$lezione->datetime_lezione : '';
        $dtObj = parseXmlDate($dt_str);
        if ($dtObj === false) {
            error_log("Data invalida per lezione id=$id: '$dt_str'");
            continue;
        }
        $dt_ts = $dtObj->getTimestamp();

        $nome = $corsoBase['nome'];
        $desc = $corsoBase['descrizione'];
        $tot = isset($lezione->posti_totali) ? (int)$lezione->posti_totali : 0;
        if ($tot < 0) $tot = 0;

        $allLezioni[$id] = [
            'id' => $id,
            'id_corso_base' => $id_corso_base,
            'nome' => $nome,
            'descrizione' => $desc,
            'datetime' => date('Y-m-d H:i:s', $dt_ts),
            'timestamp' => $dt_ts,
            'posti_totali' => $tot
        ];

        if (!isset($corsiMap[$nome])) {
            $corsiMap[$nome] = [
                'nome' => $nome,
                'descrizione' => $desc,
                'lezioni' => []
            ];
        }
        $corsiMap[$nome]['lezioni'][$id] = $allLezioni[$id];

        if ($dt_ts >= $now_ts) {
            $pren_count = 0;
            $pren_list = [];

            $pren = $lezione->xpath('prenotanti/prenotante') ?: [];
            foreach ($pren as $p) {
                $stato = getAttr($p, 'stato', 'confermato');
                if ($stato === 'cancellato') continue;

                $pren_count++;
                $pren_list[] = [
                    'id' => (int)getAttr($p, 'id', 0),
                    'user' => getAttr($p, 'user', ''),
                    'id_abb' => getAttr($p, 'id_abb', '')
                ];
            }

            $lezioni[$id] = [
                'id_lezione' => $id,
                'id_corso_base' => $id_corso_base,
                'nome' => $nome,
                'descrizione' => $desc,
                'datetime' => date('Y-m-d H:i:s', $dt_ts),
                'timestamp' => $dt_ts,
                'posti_totali' => $tot,
                'prenotati' => $pren_count,
                'prenotanti_list' => $pren_list
            ];
        }
    }
}


$prenotazioni_per_lezione = [];
$prenotazioni_utente = [];

if ($id_user && $has_active_abbonamento && $lezioniXML) {
    foreach ($lezioniXML->lezione as $lezione) {
        $lidAttr = getAttr($lezione, 'id', '');
        if ($lidAttr === '') continue;
        $lid = (int)$lidAttr;

        $pren = $lezione->xpath('prenotanti/prenotante') ?: [];
        foreach ($pren as $p) {
            $stato = getAttr($p, 'stato', 'confermato');
            if ($stato === 'cancellato') continue;

            $p_id_abb = getAttr($p, 'id_abb', '');
            if ($p_id_abb !== '' && $p_id_abb === $id_user_abbonamento_attivo) {
                $prenotazioni_per_lezione[$lid] = true;

                $corsoBase = $corsiBaseMap[(int)$lezione->id_corso_base] ?? null;
                $nome_corso = $corsoBase ? $corsoBase['nome'] : 'Corso Sconosciuto';

                $prenotazioni_utente[] = [
                    'id_lezione' => $lid,
                    'id_prenotazione' => (int)getAttr($p, 'id', 0),
                    'id_lezione' => $lid,
                    'id_abb' => $p_id_abb,
                    'nome_corso' => $nome_corso,
                    'datetime' => isset($lezione->datetime_lezione) ? (string)$lezione->datetime_lezione : ''
                ];
            }
        }
    }
}


$feedbackXMLPath = __DIR__ . '/xml/feedback.xml';
$feedbackXML = getXML($feedbackXMLPath);
$feedbacks = [];
$corsiStats = [];

if ($feedbackXML) {
    validateXMLWithDTD($feedbackXMLPath);

    foreach ($feedbackXML->valutazione_corso as $f) {
        $lid = isset($f->id_lezione) ? (int)$f->id_lezione : 0;
        $v = isset($f->voto) ? (int)$f->voto : 0;

        $lezione = $allLezioni[$lid] ?? null;
        if (!$lezione) continue;

        $corsoNome = $lezione['nome'];

        if (!isset($corsiStats[$corsoNome])) {
            $corsiStats[$corsoNome] = [
                'cnt' => 0,
                'sum' => 0,
                'voti' => []
            ];
        }

        $corsiStats[$corsoNome]['cnt']++;
        $corsiStats[$corsoNome]['sum'] += $v;
        $corsiStats[$corsoNome]['voti'][] = $v;

        if ($id_user && $has_active_abbonamento
            && (string)$f->id_user_abbonamento === $id_user_abbonamento_attivo) {
            $feedbacks[$lid] = $v;
        }
    }
}

foreach ($corsiStats as $nome => $data) {
    $corsiStats[$nome]['avg'] = $data['cnt'] ? round($data['sum'] / $data['cnt'], 2) : 0;
}


$adminCorsiBase = [];
if ($is_admin) {
    $lessonCountPerCourseBase = [];
    foreach ($allLezioni as $lezione) {
        $corsoBaseId = $lezione['id_corso_base'];
        if (!isset($lessonCountPerCourseBase[$corsoBaseId])) {
            $lessonCountPerCourseBase[$corsoBaseId] = 0;
        }
        $lessonCountPerCourseBase[$corsoBaseId]++;
    }

    foreach ($corsiBaseMap as $id => $corsoBase) {
        $stats = $corsiStats[$corsoBase['nome']] ?? null;
        $adminCorsiBase[$id] = [
            'id' => $id,
            'nome' => $corsoBase['nome'],
            'descrizione' => $corsoBase['descrizione'],
            'difficolta' => $corsoBase['difficolta'],
            'num_lezioni' => $lessonCountPerCourseBase[$id] ?? 0,
            'num_feedback' => $stats['cnt'] ?? 0,
            'avg_feedback' => $stats['avg'] ?? 0
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PALESTRW – Corsi</title>
  <link rel="stylesheet" href="style/style_corsi.css" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
  <script>
    function prenotaLezione(id) {
      fetch('prenota_lezione.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id_lezione=' + encodeURIComponent(id)
      })
      .then(r => r.json())
      .then(d => {
        alert(d.message);
        if (d.success) location.reload();
      })
      .catch(() => alert('Errore nella prenotazione'));
    }

    function eliminaPrenotazione(id, id_lezione) {
      if (!confirm('Sei sicuro di cancellare questa prenotazione?')) return;
      fetch('elimina_prenotazione.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id_prenotazione=' + encodeURIComponent(id) + '&id_lezione=' + encodeURIComponent(id_lezione)
      })
      .then(r => r.json())
      .then(d => {
        alert(d.message);
        if (d.success) location.reload();
      })
      .catch(() => alert('Errore nella cancellazione'));
    }

    function toggleElement(id, prefix) {
      const el = document.getElementById(prefix + id);
      if (!el) return;
      const current = window.getComputedStyle(el).display;
      if (current === 'none') {
        el.style.display = el.tagName.toLowerCase() === 'tr' ? 'table-row' : 'block';
      } else {
        el.style.display = 'none';
      }
    }

    function toggleAddCourseForm() {
      const form = document.getElementById('addCourseForm');
      if (!form) return;
      form.style.display = form.style.display === 'none' ? 'block' : 'none';
      const lessonForm = document.getElementById('addLessonForm');
      if (lessonForm) lessonForm.style.display = 'none';
    }

    function toggleAddLessonForm() {
      const form = document.getElementById('addLessonForm');
      if (!form) return;
      form.style.display = form.style.display === 'none' ? 'block' : 'none';
      const courseForm = document.getElementById('addCourseForm');
      if (courseForm) courseForm.style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.star-rating').forEach(r => {
        const lezioneId = r.dataset.lezione;
        r.querySelectorAll('.star').forEach((star, i) => {
          star.addEventListener('click', e => {
            e.preventDefault();
            const voto = parseInt(star.dataset.value, 10) || 0;
            fetch('save_feedback.php', {
              method: 'POST',
              headers: {'Content-Type': 'application/x-www-form-urlencoded'},
              body: 'id_lezione=' + encodeURIComponent(lezioneId) + '&voto=' + encodeURIComponent(voto)
            })
            .then(res => res.json())
            .then(d => {
              if (d.success) {
                r.querySelectorAll('.star').forEach((s, j) => {
                  if (j < voto) s.classList.add('filled'); else s.classList.remove('filled');
                });
                alert('Valutazione salvata!');
              } else {
                alert('Errore: ' + (d.message || 'Salvataggio non riuscito'));
              }
            })
            .catch(() => alert('Errore nel salvataggio'));
          });
        });
      });
    });
  </script>
</head>
<body>
  <header class="main-header">
    <div class="logo">PALESTRW</div>
    <nav>
      <ul class="main-menu">
        <?php if (!$id_user): ?>
          <li><a href="login.php"><img src="icon/area_riservata.png" class="icon" alt="">Area Riservata</a></li>
          <li><a href="home_page.php"><img src="icon/home.png" class="icon" alt="">Home</a></li>
          <li><a href="promo.php"><img src="icon/promo.png" class="icon" alt="">Promo</a></li>
          <li><a href="Chi_Siamo.php"><img src="icon/chi_siamo.png" class="icon" alt="">Chi Siamo</a></li>
          <li><a href="faq.php"><img src="icon/faq.png" class="icon">FAQ</a></li>
        <?php else: ?>
          <li><a href="home_page.php"><img src="icon/home.png" class="icon" alt="">Home</a></li>
          <li><a href="promo.php"><img src="icon/promo.png" class="icon" alt="">Promo</a></li>
          <li><a href="Chi_Siamo.php"><img src="icon/chi_siamo.png" class="icon" alt="">Chi Siamo</a></li>
          <li><a href="faq.php"><img src="icon/faq.png" class="icon">FAQ</a></li>
          <li><a href="profilo.php"><img src="icon/profilo.png" class="icon" alt="">Profilo</a></li>
          <li><a href="logout.php"><img src="icon/logout.png" class="icon" alt="">Logout</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </header>

  <main>
    <h1>Le nostre lezioni</h1>
    <?php if ($is_admin): ?>
      <div class="add-course-btn-container">
        <button onclick="toggleAddCourseForm()" class="btn-add-course">
          <i class="fas fa-plus"></i> Aggiungi Nuovo Corso Base
        </button>
        <button onclick="toggleAddLessonForm()" class="btn-add-lesson">
          <i class="fas fa-plus"></i> Aggiungi Nuova Lezione
        </button>
      </div>
    <?php endif; ?>

    <?php if ($is_admin): ?>
      <form id="addCourseForm" action="aggiungi_corso_base.php" method="POST" style="display:none;">
        <h2>Aggiungi Nuovo Corso Base</h2>
        <div class="form-grid">
          <div class="form-group">
            <label for="nome">Nome Corso:</label>
            <input type="text" id="nome" name="nome" required>
          </div>

          <div class="form-group">
            <label for="descrizione">Descrizione:</label>
            <textarea id="descrizione" name="descrizione" rows="4" required></textarea>
          </div>

          <div class="form-group">
            <label for="difficolta">Difficoltà:</label>
            <select id="difficolta" name="difficolta" required>
              <option value="1">Facile</option>
              <option value="2">Medio</option>
              <option value="3">Difficile</option>
            </select>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-submit">Salva Corso</button>
          <button type="button" class="btn-cancel" onclick="toggleAddCourseForm()">Annulla</button>
        </div>
      </form>

      <form id="addLessonForm" action="aggiungi_lezione.php" method="POST" style="display:none;">
        <h2>Aggiungi Nuova Lezione</h2>
        <div class="form-grid">
          <div class="form-group">
            <label for="id_corso_base">Corso Base:</label>
            <select id="id_corso_base" name="id_corso_base" required>
              <?php foreach ($corsiBaseMap as $corsoBase): ?>
                <option value="<?= (int)$corsoBase['id'] ?>"><?= h($corsoBase['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="datetime_lezione">Data e Ora Lezione:</label>
            <input type="datetime-local" id="datetime_lezione" name="datetime_lezione" required>
          </div>

          <div class="form-group">
            <label for="posti_totali">Posti Totali:</label>
            <input type="number" id="posti_totali" name="posti_totali" min="1" required>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-submit">Salva Lezione</button>
          <button type="button" class="btn-cancel" onclick="toggleAddLessonForm()">Annulla</button>
        </div>
      </form>
    <?php endif; ?>

    <?php if (empty($lezioni)): ?>
      <div class="no-data">
        <i class="fas fa-calendar-times fa-3x"></i>
        <h3>Nessuna lezione in programma</h3>
        <p>Al momento non ci sono lezioni disponibili. Torna a controllare presto!</p>
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Corso</th><th>Descrizione</th><th>Data &amp; Ora</th>
            <th>Prenota</th><th>Prenotati</th>
            <?php if($is_admin): ?><th>Modifica</th><th>Elimina</th><?php endif;?>
          </tr>
        </thead>
        <tbody>
          <?php foreach($lezioni as $id => $l):
            $disp = max(0, $l['posti_totali'] - $l['prenotati']);
          ?>
          <tr data-lezione-id="<?= (int)$id ?>">
            <td><?= h($l['nome']) ?></td>
            <td><?= nl2br(h($l['descrizione'])) ?></td>
            <td><?= date('d/m/Y H:i', (int)$l['timestamp']) ?></td>
            <td>
              <?php if($is_admin): ?>
                <em>Admin non può prenotare</em>
              <?php elseif(!$id_user || !$has_active_abbonamento): ?>
                <em>Login + Abbonamento</em>
              <?php else: ?>
                <?php if(isset($prenotazioni_per_lezione[$id])): ?>
                  <strong>Già prenotato</strong>
                <?php elseif($disp <= 0): ?>
                  <em>Esaurito</em>
                <?php else: ?>
                  <button class="btn-prenota" onclick="prenotaLezione(<?= (int)$id ?>)">Prenota</button>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td>
              <?= (int)$l['prenotati'] . '/' . (int)$l['posti_totali'] ?>
              <?php if($is_admin): ?>
                <button onclick="toggleElement(<?= (int)$id ?>,'pren-list-')"><i class="fas fa-eye"></i></button>
              <?php endif; ?>
            </td>
            <?php if($is_admin): ?>
              <td><button onclick="toggleElement(<?= (int)$id ?>,'edit-form-')" class="edit-btn">✏️</button></td>
              <td><a href="cancella_lezione.php?id=<?= (int)$id ?>" class="btn-elimina" onclick="return confirm('Sei sicuro di eliminare la lezione?')">❌</a></td>
            <?php endif; ?>
          </tr>
          <?php if($is_admin): ?>
          <tr id="edit-form-<?= (int)$id ?>" style="display:none;">
            <td colspan="7">
              <form action="modifica_lezione.php" method="POST" class="inline-form">
                <input type="hidden" name="id_lezione" value="<?= (int)$id ?>">
                <label>Data &amp; Ora:
                  <input type="datetime-local" name="datetime_lezione"
                    value="<?= date('Y-m-d\TH:i', (int)$l['timestamp']) ?>" required>
                </label>
                <label>Posti:
                  <input type="number" name="posti_totali" min="1"
                    value="<?= (int)$l['posti_totali'] ?>" required>
                </label>
                <button type="submit" class="btn-submit">Salva</button>
                <button type="button" class="btn-cancel" onclick="toggleElement(<?= (int)$id ?>,'edit-form-')">Annulla</button>
              </form>
            </td>
          </tr>
          <tr id="pren-list-<?= (int)$id ?>" style="display:none;">
            <td colspan="7">
              <div class="pren-list">
                <h4>Prenotanti per <?= h($l['nome']) ?></h4>
                <ul>
                  <?php foreach($l['prenotanti_list'] as $p): ?>
                    <li><i class="fas fa-user"></i> <?= h($p['user']) ?> (ID <?= (int)$p['id'] ?>)</li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </td>
          </tr>
          <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php
      $future = [];
      $now = time();
      foreach($prenotazioni_utente as $pr) {
        $ts = strtotime($pr['datetime']);
        if ($ts !== false && $ts > $now) $future[] = $pr;
      }
    ?>
    <?php if(!$is_admin && $id_user && $has_active_abbonamento && !empty($future)): ?>
      <h2>Le tue prenotazioni future</h2>
      <table class="prenotazioni-table">
        <thead><tr><th>Corso</th><th>Data</th><th>Elimina</th></tr></thead>
        <tbody>
          <?php foreach($future as $pr):
            $dtf = strtotime($pr['datetime']);
            $diff = $dtf - $now;
          ?>
          <tr data-booking-id="<?= (int)$pr['id_prenotazione'] ?>">
            <td><?= h($pr['nome_corso']) ?></td>
            <td><?= date('d/m/Y H:i', $dtf) ?></td>
            <td>
              <?php if($diff >= 7200): ?>
                <button class="btn-elimina" onclick="eliminaPrenotazione(<?= (int)$pr['id_prenotazione'] ?>,<?= (int)$pr['id_lezione'] ?>)">✖️</button>
              <?php else: ?>
                <em>Scaduto</em>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if(!$is_admin && $id_user && $has_active_abbonamento): ?>
      <?php
        $lezioni_passate = [];
        $now_ts2 = time();
        foreach($allLezioni as $lid => $l) {
          if ((int)$l['timestamp'] < $now_ts2) {
            if (isset($prenotazioni_per_lezione[$lid]) && $prenotazioni_per_lezione[$lid]) {
              $lezioni_passate[$lid] = $l;
            }
          }
        }
      ?>
      <?php if(!empty($lezioni_passate)): ?>
        <h2>Lezioni già svolte: lascia una valutazione</h2>
        <table class="lezioni-svolte-table">
          <thead><tr><th>Corso</th><th>La tua valutazione</th></tr></thead>
          <tbody>
            <?php foreach($lezioni_passate as $lid => $l):
              $v = $feedbacks[$lid] ?? 0;
            ?>
            <tr>
              <td><?= h($l['nome']) ?> (<?= date('d/m/Y H:i', (int)$l['timestamp']) ?>)</td>
              <td>
                <div class="star-rating" data-lezione="<?= (int)$lid ?>">
                  <?php for($i = 1; $i <= 5; $i++): ?>
                    <span class="star <?= ($i <= $v ? 'filled' : '') ?>" data-value="<?= $i ?>">★</span>
                  <?php endfor; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    <?php endif; ?>

    <?php
      $difficultyMap = [
        1 => 'Facile',
        2 => 'Medio',
        3 => 'Difficile'
      ];
    ?>

    <?php if($is_admin && !empty($adminCorsiBase)): ?>
      <h2>Gestione Corsi Base</h2>
      <table class="stats-table">
        <thead>
          <tr>
            <th>Nome</th>
            <th>Descrizione</th>
            <th>Difficoltà</th>
            <th>Lezioni</th>
            <th>Feedback</th>
            <th>Media</th>
            <th>Modifica</th>
            <th>Elimina</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($adminCorsiBase as $id => $corso): ?>
          <tr>
            <td><?= h($corso['nome']) ?></td>
            <td><?= nl2br(h($corso['descrizione'])) ?></td>
            <td><?= $difficultyMap[$corso['difficolta']] ?? h($corso['difficolta']) ?></td>
            <td><?= (int)$corso['num_lezioni'] ?></td>
            <td><?= (int)$corso['num_feedback'] ?></td>
            <td>
              <?php if($corso['num_feedback']): ?>
                <div class="bar-container" title="<?= number_format($corso['avg_feedback'],2) ?>">
                  <?php $vRounded=(int)round($corso['avg_feedback']);?>
                 <div class="bar v<?= $vRounded ?>" style="width:<?= max(0, min(100, $corso['avg_feedback'] * 20)) ?>%"></div>

                </div>
                <small><?= number_format($corso['avg_feedback'], 2) ?></small>
              <?php else: ?>-<?php endif; ?>
            </td>
            <td><button onclick="toggleElement(<?= (int)$id ?>,'edit-corso-base-')" class="edit-btn">✏️</button></td>
            <td><a href="cancella_corso_base.php?id=<?= (int)$id ?>" onclick="return confirm('Sei sicuro di voler eliminare questo corso base? Verranno eliminate anche tutte le lezioni associate.')" class="btn-elimina">❌</a></td>
          </tr>
          <tr id="edit-corso-base-<?= (int)$id ?>" style="display:none;">
            <td colspan="8">
              <form action="modifica_corso_base.php" method="POST" class="inline-form">
                <input type="hidden" name="id_corso_base" value="<?= (int)$id ?>">
                <div class="form-grid">
                  <div class="form-group">
                    <label for="nome_<?= (int)$id ?>">Nome Corso:</label>
                    <input type="text" id="nome_<?= (int)$id ?>" name="nome" value="<?= h($corso['nome']) ?>" required>
                  </div>
                  <div class="form-group">
                    <label for="descrizione_<?= (int)$id ?>">Descrizione:</label>
                    <textarea id="descrizione_<?= (int)$id ?>" name="descrizione" rows="4" required><?= h($corso['descrizione']) ?></textarea>
                  </div>
                  <div class="form-group">
                    <label for="difficolta_<?= (int)$id ?>">Difficoltà:</label>
                    <select id="difficolta_<?= (int)$id ?>" name="difficolta" required>
                      <option value="1" <?= $corso['difficolta'] == 1 ? 'selected' : '' ?>>Facile</option>
                      <option value="2" <?= $corso['difficolta'] == 2 ? 'selected' : '' ?>>Medio</option>
                      <option value="3" <?= $corso['difficolta'] == 3 ? 'selected' : '' ?>>Difficile</option>
                    </select>
                  </div>
                </div>
                <div class="form-actions">
                  <button type="submit" class="btn-submit">Salva Modifiche</button>
                  <button type="button" class="btn-cancel" onclick="toggleElement(<?= (int)$id ?>,'edit-corso-base-')">Annulla</button>
                </div>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

  </main>

  <footer id="footer">
    <p>&copy; <?= date('Y') ?> PALESTRW. Tutti i diritti riservati.</p>
  </footer>
</body>
</html>
