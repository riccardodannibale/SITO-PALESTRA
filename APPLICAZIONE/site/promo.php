<?php
session_start();

$today = new DateTimeImmutable('now', new DateTimeZone('Europe/Rome'));

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
    }
}

define('PROMO_XML', __DIR__ . '/xml/promo.xml');
define('PROMO_DTD', __DIR__ . '/dtd/promo.dtd');
define('ABB_XML', __DIR__ . '/xml/abbonamenti.xml');
define('ABB_DTD', __DIR__ . '/dtd/abbonamenti.dtd');

function validateXmlAgainstDtd(string $path, string $dtdPath): bool {
    libxml_use_internal_errors(true);

    $xmlStr = @file_get_contents($path);
    if ($xmlStr === false) {
        error_log("validateXmlAgainstDtd: impossibile leggere $path");
        return false;
    }

    $dtdStr = @file_get_contents($dtdPath);
    $needsIdPrefix = false;
    if ($dtdStr !== false) {
        if (preg_match('/<!ATTLIST\s+\w+\s+id\s+ID\b/i', $dtdStr) || preg_match('/\bid\s+ID\b/i', $dtdStr)) {
            $needsIdPrefix = true;
        }
    }

    if ($needsIdPrefix) {
        $xmlStr = preg_replace_callback(
            '/\b(id\s*=\s*")(\d+)(")/i',
            function ($m) { return $m[1] . 'n' . $m[2] . $m[3]; },
            $xmlStr
        );
    }

    $tmp = new DOMDocument('1.0', 'UTF-8');
    $tmp->preserveWhiteSpace = false;
    $tmp->formatOutput = true;

    $ok = @$tmp->loadXML($xmlStr, LIBXML_DTDVALID);
    if (!$ok) {
        $errs = libxml_get_errors();
        foreach ($errs as $e) {
            error_log("LibXML (validateXmlAgainstDtd) $path: " . trim($e->message) .
                      " at line " . $e->line . " column " . $e->column);
        }
        libxml_clear_errors();
        return false;
    }
    libxml_clear_errors();
    return true;
}

function loadXmlWithDtd(string $path, string $dtdPath, string $rootName): DOMDocument {
    libxml_use_internal_errors(true);

    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput    = true;
    $doc->validateOnParse = false; 

    $relativeDtdPath = '../dtd/' . basename($dtdPath);

    if (!file_exists($path)) {
        $impl = new DOMImplementation();
        $systemId = is_file($dtdPath) ? ('file://' . realpath($dtdPath)) : $relativeDtdPath;
        $dtd  = $impl->createDocumentType($rootName, '', $systemId);
        $newDoc  = $impl->createDocument(null, '', $dtd);
        $newDoc->encoding = 'UTF-8';
        $root = $newDoc->createElement($rootName);
        $newDoc->appendChild($root);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $newDoc->save($path);
        $doc = $newDoc;
    }

    $loaded = $doc->load($path);
    if ($loaded === false) {
        $errs = libxml_get_errors();
        foreach ($errs as $e) {
            error_log("LibXML load error ($path): " . trim($e->message));
        }
        libxml_clear_errors();
        throw new RuntimeException("Impossibile caricare XML: $path");
    }

    if (!$doc->doctype) {
        $impl = new DOMImplementation();
        $systemId = is_file($dtdPath) ? ('file://' . realpath($dtdPath)) : $relativeDtdPath;
        $dtd  = $impl->createDocumentType($rootName, '', $systemId);
        $newDoc = $impl->createDocument(null, '', $dtd);
        $newDoc->encoding = 'UTF-8';
        if ($doc->documentElement) {
            $newDoc->appendChild($newDoc->importNode($doc->documentElement, true));
        } else {
            $root = $newDoc->createElement($rootName);
            $newDoc->appendChild($root);
        }
        $doc = $newDoc;
    }

  
    $valid = validateXmlAgainstDtd($path, $dtdPath);

    if (!$valid) {
        $isEmptyDocument = !$doc->documentElement || !$doc->documentElement->hasChildNodes();
        if ($isEmptyDocument) {
            libxml_clear_errors();
        } else {
            throw new RuntimeException("Errore di validazione XML/DTD su " . $path);
        }
    }

    libxml_clear_errors();
    return $doc;
}

function saveXml(DOMDocument $doc, string $path): void {
    $doc->save($path);
}

function getAbbonamenti(): array {
    $xml = loadXmlWithDtd(ABB_XML, ABB_DTD, 'abbonamenti');
    $list = [];
    foreach ($xml->getElementsByTagName('abbonamento') as $node) {
        $id   = $node->getAttribute('id');
        $tipoNode = $node->getElementsByTagName('tipo')->item(0);
        $tipo = $tipoNode ? trim($tipoNode->textContent) : '';
        $list[$id] = $tipo;
    }
    return $list;
}

if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $abbonamenti_validi = getAbbonamenti();
    $tipi = array_values(array_unique(array_values($abbonamenti_validi)));

    $applica_input = $_POST['applica'] ?? null;
    $selected_tipi = [];

    if (is_array($applica_input)) {
        foreach ($applica_input as $v) {
            $v = trim((string)$v);
            if ($v !== '') $selected_tipi[] = $v;
        }
    } elseif (is_string($applica_input)) {
        $v = trim($applica_input);
        if ($v !== '') $selected_tipi[] = $v;
    }

    if (empty($selected_tipi)) {
        die("Errore: selezionare almeno un tipo di abbonamento a cui applicare la promozione.");
    }

    foreach ($selected_tipi as $t) {
        if (!in_array($t, $tipi, true)) {
            die("Errore: tipo selezionato non valido: " . htmlspecialchars($t));
        }
    }

    $doc  = loadXmlWithDtd(PROMO_XML, PROMO_DTD, 'promozioni');
    $root = $doc->documentElement;
    $last = 0;
    foreach ($doc->getElementsByTagName('promo') as $p) {
        $id = (int)$p->getAttribute('id');
        $last = max($last, $id);
    }
    $newId = $last + 1;

    $promo = $doc->createElement('promo');
    $promo->setAttribute('id', (string)$newId);

    $fields = [
        'titolo', 'descrizione', 'data_inizio', 'data_fine',
        'codice_sconto'
    ];
    foreach ($fields as $field) {
        $val = htmlspecialchars(trim($_POST[$field] ?? ''), ENT_XML1, 'UTF-8');
        $element = $doc->createElement($field, $val);
        $promo->appendChild($element);
    }

    $attiva = $doc->createElement('attiva', '0');
    $promo->appendChild($attiva);

    if (isset($_POST['percentuale']) && trim((string)$_POST['percentuale']) !== '') {
        $valNum = number_format((float)$_POST['percentuale'], 2, '.', '');
        $element = $doc->createElement('percentuale', $valNum);
        $promo->appendChild($element);
    }

    $applicaEl = $doc->createElement('applica');
    foreach ($selected_tipi as $t) {
        $tipoEl = $doc->createElement('tipo', htmlspecialchars($t, ENT_XML1, 'UTF-8'));
        $applicaEl->appendChild($tipoEl);
    }
    $promo->appendChild($applicaEl);

    $root->appendChild($promo);
    saveXml($doc, PROMO_XML);
    header('Location: promo.php');
    exit;
}

if ($is_admin && isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    $doc = loadXmlWithDtd(PROMO_XML, PROMO_DTD, 'promozioni');
    $xpath = new DOMXPath($doc);
    foreach ($xpath->query("//promo[@id='" . $deleteId . "']") as $n) {
        $n->parentNode->removeChild($n);
    }
    saveXml($doc, PROMO_XML);
    header('Location: promo.php');
    exit;
}

$doc = loadXmlWithDtd(PROMO_XML, PROMO_DTD, 'promozioni');
$mod   = false;
foreach ($doc->getElementsByTagName('promo') as $p) {
    $startNode = $p->getElementsByTagName('data_inizio')->item(0);
    $endNode   = $p->getElementsByTagName('data_fine')->item(0);
    $actNode   = $p->getElementsByTagName('attiva')->item(0);

    if (!$startNode || !$endNode) continue;
    if (!$actNode) {
        $actNode = $doc->createElement('attiva', '0');
        $p->appendChild($actNode);
    }

    try {
        $start = (new DateTimeImmutable($startNode->textContent, new DateTimeZone('Europe/Rome')))
                 ->setTime(0, 0, 0); 
        $end   = (new DateTimeImmutable($endNode->textContent, new DateTimeZone('Europe/Rome')))
                 ->setTime(23, 59, 59); 

        $should = ($today >= $start && $today <= $end) ? '1' : '0';
    } catch (Exception $e) {
  
        continue;
    }

    if ($actNode->textContent !== $should) {
        $actNode->textContent = $should;
        $mod = true;
    }
}
if ($mod) saveXml($doc, PROMO_XML);


$promosNodes = $doc->getElementsByTagName('promo');
$activePromos = [];
foreach ($promosNodes as $p) {
    $startNode = $p->getElementsByTagName('data_inizio')->item(0);
    $endNode   = $p->getElementsByTagName('data_fine')->item(0);
    if (!$endNode) continue; 
    try {
       
        $end = (new DateTimeImmutable($endNode->textContent, new DateTimeZone('Europe/Rome')))
                ->setTime(23, 59, 59); 
    } catch (Exception $e) {
        continue;
    }
    
    if ($end < $today) continue;

    $id     = (int)$p->getAttribute('id');
    $titNode = $p->getElementsByTagName('titolo')->item(0);
    $descNode = $p->getElementsByTagName('descrizione')->item(0);
    $iniNode  = $p->getElementsByTagName('data_inizio')->item(0);
    $finNode  = $p->getElementsByTagName('data_fine')->item(0);
    $codNode  = $p->getElementsByTagName('codice_sconto')->item(0);

    $tit  = $titNode ? $titNode->textContent : '';
    $desc = $descNode ? $descNode->textContent : '';
    $ini  = $iniNode ? $iniNode->textContent : '';
    $fin  = $finNode ? $finNode->textContent : '';
    $cod  = $codNode ? $codNode->textContent : '';

    $percNode = $p->getElementsByTagName('percentuale')->item(0);
    $perc = $percNode ? $percNode->textContent : null;

    $applTypes = [];
    $applicaNode = $p->getElementsByTagName('applica')->item(0);
    if ($applicaNode) {
        $tipoChildren = $applicaNode->getElementsByTagName('tipo');
        if ($tipoChildren->length > 0) {
            foreach ($tipoChildren as $t) {
                $applTypes[] = $t->textContent;
            }
        } else {
            $text = trim($applicaNode->textContent);
            if ($text !== '') $applTypes[] = $text;
        }
    }

    $activePromos[] = [
        'id' => $id,
        'titolo' => $tit,
        'descrizione' => $desc,
        'data_inizio' => $ini,
        'data_fine' => $fin,
        'codice_sconto' => $cod,
        'percentuale' => $perc,
        'applica_tipi' => $applTypes,
        'endDate' => $end
    ];
}

usort($activePromos, function($a, $b){
    if ($a['endDate'] == $b['endDate']) return 0;
    return $a['endDate'] < $b['endDate'] ? -1 : 1;
});

$abbonamenti = getAbbonamenti();
$tipi = array_values(array_unique(array_values($abbonamenti)));
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>PALESTRW - Promo</title>
  <link rel="stylesheet" href="style/style_promo.css" />

</head>
<body>
  <div id="header" class="main-header">
    <div class="logo">PALESTRW</div>
    <ul class="main-menu">
      <?php if (!$id_user): ?>
        <li><a href="login.php"><img src="icon/area_riservata.png" class="icon">Area Riservata</a></li>
        <li><a href="home_page.php"><img src="icon/home.png" class="icon">Home</a></li>
        <li><a href="corsi.php"><img src="icon/corsi.png" class="icon">Corsi</a></li>
        <li><a href="Chi_Siamo.php"><img src="icon/chi_siamo.png" class="icon">Chi Siamo</a></li>
        <li><a href="faq.php"><img src="icon/faq.png" class="icon">FAQ</a></li>
      <?php else: ?>
        <li><a href="home_page.php"><img src="icon/home.png" class="icon">Home</a></li>
        <li><a href="corsi.php"><img src="icon/corsi.png" class="icon">Corsi</a></li>
        <li><a href="Chi_Siamo.php"><img src="icon/chi_siamo.png" class="icon">Chi Siamo</a></li>
        <li><a href="faq.php"><img src="icon/faq.png" class="icon">FAQ</a></li>
        <li><a href="profilo.php"><img src="icon/profilo.png" class="icon">Profilo</a></li>
        <li><a href="logout.php"><img src="icon/logout.png" class="icon">Logout</a></li>
      <?php endif; ?>
    </ul>
  </div>

  <h1 class="titolo">PROMO</h1>

  <div class="container" id="promo-container" aria-label="Elenco promozioni">
    <?php 
  
    foreach ($activePromos as $promo):
      $id = $promo['id'];
      $tit = $promo['titolo'];
      $desc = $promo['descrizione'];
      $ini = $promo['data_inizio'];
      $fin = $promo['data_fine'];
      $cod = $promo['codice_sconto'];
      $perc = $promo['percentuale'];
      $applTypes = $promo['applica_tipi'];
    ?>
      <div class="promo-item" role="article" aria-labelledby="promo-title-<?= htmlspecialchars($id) ?>">
        <div class="promo-content">
          <h2 id="promo-title-<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($tit) ?></h2>
          <p><?= nl2br(htmlspecialchars($desc)) ?></p>
          <p><strong>Periodo:</strong> <?= htmlspecialchars($ini) ?> &mdash; <?= htmlspecialchars($fin) ?></p>
          <p><strong>Codice:</strong> <?= htmlspecialchars($cod) ?></p>
          <?php if ($perc): ?>
            <p><strong>Percentuale:</strong> <?= htmlspecialchars($perc) ?>%</p>
          <?php endif; ?>
          <?php if ($applTypes && count($applTypes) > 0): ?>
            <p><strong>Applica ai tipi:</strong> <?= htmlspecialchars(implode(', ', $applTypes)) ?></p>
          <?php endif; ?>
          <?php if ($is_admin): ?>
            <a class="delete-btn" href="promo.php?delete=<?= urlencode((string)$id) ?>" onclick="return confirm('Eliminare?')">Eliminare</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if ($is_admin): ?>
      <div class="promo-add-tile" id="promo-add-tile" role="button" tabindex="0" aria-label="Aggiungi promozione">+</div>
    <?php endif; ?>
  </div>
  <?php if ($is_admin): ?>
    <div class="add-promo-form" id="add-promo-form" aria-hidden="true" style="display:none;">
      <h2>Aggiungi Promozione</h2>
      <form method="post" action="promo.php" id="form-add-promo">
        <input type="hidden" name="action" value="add" />
        <label>Titolo:<br><input type="text" name="titolo" required maxlength="150" /></label><br>
        <label>Descrizione:<br><textarea name="descrizione" rows="3" required></textarea></label><br>
        <label>Data Inizio:<br><input type="date" name="data_inizio" required /></label><br>
        <label>Data Fine:<br><input type="date" name="data_fine" required /></label><br>
        <label>Codice Sconto:<br><input type="text" name="codice_sconto" required /></label><br>
        <label>Percentuale:<br><input type="number" name="percentuale" step="0.01" min="0" max="100" /></label><br>

        <label>Applica (seleziona uno o più tipi):</label><br>
        <div class="multiselect" id="multiselect-tipi">
          <button type="button" class="multiselect-button" id="multiselect-button" aria-haspopup="listbox" aria-expanded="false" aria-controls="multiselect-panel">
            <span id="multiselect-label" class="multiselect-empty">-- Scegli tipi --</span>
            <span aria-hidden="true">▾</span>
          </button>
          <div class="multiselect-panel" id="multiselect-panel" role="listbox" aria-multiselectable="true" style="display:none;">
            <?php foreach ($tipi as $i => $tipo): ?>
              <label>
                <input type="checkbox" name="applica[]" value="<?= htmlspecialchars($tipo) ?>" />
                <?= htmlspecialchars($tipo) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <br>
        <div class="form-actions">
          <button type="button" id="cancel-add-promo" class="btn-cancel">Annulla</button>
          <button type="submit" class="btn-save">Salva</button>
        </div>
      </form>
    </div>
  <?php endif; ?>

   <footer id="footer">
    <p>&copy; <?= date('Y') ?> PALESTRW. Tutti i diritti riservati.</p>
  </footer>

  <script>
    (function(){
      const addTile = document.getElementById('promo-add-tile');
      const addForm = document.getElementById('add-promo-form');
      const cancelBtn = document.getElementById('cancel-add-promo');

      if (addTile && addForm) {
        const toggleForm = () => {
          const isHidden = addForm.style.display === 'none' || !addForm.style.display;
          if (isHidden) {
            addForm.style.display = 'block';
            addForm.setAttribute('aria-hidden','false');
            const cont = document.getElementById('promo-container');
            cont.scrollTo({ left: cont.scrollWidth, behavior: 'smooth' });
            setTimeout(()=> addForm.querySelector('input[name="titolo"]')?.focus(), 400);
          } else {
            addForm.style.display = 'none';
            addForm.setAttribute('aria-hidden','true');
          }
        };

        addTile.addEventListener('click', toggleForm);
        addTile.addEventListener('keydown', function(e){
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggleForm();
          }
        });

        cancelBtn?.addEventListener('click', function(){
          addForm.style.display = 'none';
          addForm.setAttribute('aria-hidden','true');
        });
      }

      document.getElementById('form-add-promo')?.addEventListener('submit', function(e) {
        const checked = this.querySelectorAll('input[name="applica[]"]:checked');
        if (checked.length === 0) {
          e.preventDefault();
          alert('Selezionare almeno un tipo di abbonamento a cui applicare la promozione!');
          togglePanel(true);
        }
      });

      const multi = document.getElementById('multiselect-tipi');
      if (multi) {
        const btn = document.getElementById('multiselect-button');
        const panel = document.getElementById('multiselect-panel');
        const label = document.getElementById('multiselect-label');

        function updateLabel() {
          const checkedBoxes = panel.querySelectorAll('input[type="checkbox"]:checked');
          if (checkedBoxes.length === 0) {
            label.textContent = '-- Scegli tipi --';
            label.classList.add('multiselect-empty');
          } else if (checkedBoxes.length === 1) {
            label.textContent = checkedBoxes[0].parentNode.textContent.trim();
            label.classList.remove('multiselect-empty');
          } else {
            const texts = Array.from(checkedBoxes).map(cb => cb.parentNode.textContent.trim());
            label.textContent = texts.join(', ');
            label.classList.remove('multiselect-empty');
          }
        }

        function togglePanel(forceOpen) {
          const isOpen = panel.style.display !== 'none' && panel.style.display !== '';
          const open = (typeof forceOpen === 'boolean') ? forceOpen : !isOpen;
          if (open) {
            panel.style.display = 'block';
            btn.setAttribute('aria-expanded', 'true');
          } else {
            panel.style.display = 'none';
            btn.setAttribute('aria-expanded', 'false');
          }
        }

        btn.addEventListener('click', function(e){
          e.preventDefault();
          togglePanel();
        });

        document.addEventListener('click', function(e){
          if (!multi.contains(e.target)) {
            togglePanel(false);
          }
        });

        btn.addEventListener('keydown', function(e){
          if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            togglePanel(true);
            const first = panel.querySelector('input[type="checkbox"]');
            if (first) first.focus();
          } else if (e.key === 'Escape') {
            togglePanel(false);
            btn.focus();
          }
        });

        panel.addEventListener('keydown', function(e){
          if (e.key === 'Escape') {
            togglePanel(false);
            btn.focus();
          }
        });

        panel.querySelectorAll('input[type="checkbox"]').forEach(function(cb){
          cb.addEventListener('change', updateLabel);
        });

        updateLabel();
      }
    })();
  </script>
</body>
</html>
