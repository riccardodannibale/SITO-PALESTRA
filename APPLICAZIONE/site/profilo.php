<?php
session_start();

if (isset($_SESSION['cp_success'])) {
    $cp_success = $_SESSION['cp_success'];
    unset($_SESSION['cp_success']);
} else {
    $cp_success = '';
}

if (isset($_SESSION['cp_error'])) {
    $cp_error = $_SESSION['cp_error'];
    unset($_SESSION['cp_error']);
} else {
    $cp_error = '';
}

if (isset($_SESSION['add_success'])) {
    $add_success = $_SESSION['add_success'];
    unset($_SESSION['add_success']);
} else {
    $add_success = '';
}

if (isset($_SESSION['add_error'])) {
    $add_error = $_SESSION['add_error'];
    unset($_SESSION['add_error']);
} else {
    $add_error = '';
}

if (isset($_SESSION['add_type_success'])) {
    $add_type_success = $_SESSION['add_type_success'];
    unset($_SESSION['add_type_success']);
} else {
    $add_type_success = '';
}

if (isset($_SESSION['add_type_error'])) {
    $add_type_error = $_SESSION['add_type_error'];
    unset($_SESSION['add_type_error']);
} else {
    $add_type_error = '';
}

$force_password_change = $_SESSION['force_pw_change'] ?? false;

$id_user  = $_SESSION['id_user'] ?? null;
$is_admin = false;
$currentUserData = [];

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit;
}

include __DIR__ . '/db.php';

if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function getXML($path, $root = null) {
    if (!file_exists($path) && $root) {
        $xml = new SimpleXMLElement("<?xml version=\"1.0\"?><{$root}></{$root}>");
        $xml->asXML($path);
        return $xml;
    }
    if (!file_exists($path)) {
        return null;
    }
    
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($path);
    if ($xml === false) {
        error_log("Errore caricamento XML: " . $path);
        foreach (libxml_get_errors() as $error) {
            error_log($error->message);
        }
        libxml_clear_errors();
        return null;
    }
    return $xml;
}

function saveXML($xml, $path) {
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    return $dom->save($path);
}

function parseDiscountFraction($s) {
    $s = trim((string)$s);
    if ($s === '') return 0.0;
    $s = str_replace(',', '.', $s);
    if (substr($s, -1) === '%') $s = rtrim($s, '%');
    $val = floatval($s);
    if ($val > 1) $val = $val / 100.0;
    return max(0.0, min(1.0, $val));
}

function getSubscriptionStatus($data_inizio, $data_scadenza, $today = null) {
    if (!$today) $today = date('Y-m-d');
    if (empty($data_scadenza)) return 'scaduto';
    try {
        $dToday = new DateTime($today);
        $dEnd = new DateTime($data_scadenza);
    } catch (Exception $e) {
        return 'scaduto';
    }
    if ($dEnd < $dToday) return 'scaduto';
    $interval = $dToday->diff($dEnd);
    $days = (int)$interval->days;
    if ($days <= 7) return 'in_scadenza';
    return 'attivo';
}

function promoApplicableIdsFromXmlNode($promoNode, $abbonamenti) {
    $ids = [];
    $applicableTypes = [];

    if (isset($promoNode->applica)) {
        foreach ($promoNode->applica->tipo as $tipoNode) {
            $tipoName = trim((string)$tipoNode);
            if (!empty($tipoName)) {
                $applicableTypes[] = $tipoName;
            }
        }
    }

    foreach ($abbonamenti as $abbonamento) {
        if (in_array($abbonamento['nome'], $applicableTypes)) {
            $ids[] = $abbonamento['id'];
        }
    }

    return array_unique($ids);
}

if ($id_user) {
    $stmt = $conn->prepare("SELECT username, email, is_admin, indirizzo AS address, telefono AS phone FROM users WHERE id_user = ?");
    $stmt->bind_param("i", $id_user);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($username, $email, $flag_admin, $address, $phone);
    $stmt->fetch();
    $stmt->free_result();
    $stmt->close();
    $is_admin = ($flag_admin == 1);

    $currentUserData = [
        'username'    => $username,
        'email'       => $email,
        'indirizzo'   => $address,
        'telefono'    => $phone,
        'reputazione' => 0
    ];

  $xmlReput = getXML(__DIR__ . '/xml/reputazione.xml', 'reputazioni');
    if ($xmlReput) {
        foreach ($xmlReput->utente as $u) {
            $attr = $u->attributes();
            if ((int)$attr['id'] === $id_user) {
                $currentUserData['reputazione'] = (float)$u->punteggio;
                break;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $uid = $_SESSION['id_user'];
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($new) || empty($confirm)) {
        $_SESSION['cp_error'] = 'Compila tutti i campi';
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id_user = ?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $_SESSION['cp_error'] = 'Utente non trovato';
        } else {
            $stmt->bind_result($hash);
            $stmt->fetch();
            if (!password_verify($current, $hash)) {
                $_SESSION['cp_error'] = 'Password attuale non corretta';
            } elseif (strlen($new) < 8) {
                $_SESSION['cp_error'] = 'La password deve avere almeno 8 caratteri';
            } elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) {
                $_SESSION['cp_error'] = 'La password deve contenere almeno 1 numero e 1 lettera maiuscola';
            } elseif ($new !== $confirm) {
                $_SESSION['cp_error'] = 'Le password non coincidono';
            } else {
                $new_hash = password_hash($new, PASSWORD_DEFAULT);
                $upd = $conn->prepare(
                    'UPDATE users SET password = ?, password_temp = 0, force_pw_change = 0 WHERE id_user = ?'
                );
                $upd->bind_param('si', $new_hash, $uid);
                if ($upd->execute()) {
                    $_SESSION['cp_success'] = 'Password aggiornata con successo';
                    $_SESSION['force_pw_change'] = false;
                    header('Location: profilo.php');
                    exit;
                } else {
                    $_SESSION['cp_error'] = 'Errore durante l\'aggiornamento';
                }
                $upd->close();
            }
        }
        $stmt->close();
    }
    header('Location: profilo.php');
    exit;
}

$profile_error   = '';
$profile_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $uid            = $_SESSION['id_user'];
    $new_username   = $_POST['username'] ?? '';
    $new_email      = $_POST['email'] ?? '';
    $new_indirizzo  = $_POST['indirizzo'] ?? '';
    $new_telefono   = $_POST['telefono'] ?? '';

    if (empty($new_username) || empty($new_email) || empty($new_indirizzo) || empty($new_telefono)) {
        $profile_error = 'Compila tutti i campi';
    } else {
        $fields = [
            'username' => 'SELECT id_user FROM users WHERE username = ? AND id_user != ?',
            'email'    => 'SELECT id_user FROM users WHERE email = ? AND id_user != ?',
            'telefono' => 'SELECT id_user FROM users WHERE telefono = ? AND id_user != ?'
        ];
        foreach ($fields as $field => $query) {
            if (!$profile_error) {
                $val  = ${"new_" . $field};
                $stmt = $conn->prepare($query);
                $stmt->bind_param('si', $val, $uid);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $profile_error = ucfirst($field) . ' già in uso';
                }
                $stmt->close();
            }
        }
        if (!$profile_error) {
            $upd = $conn->prepare(
                'UPDATE users SET username = ?, email = ?, indirizzo = ?, telefono = ? WHERE id_user = ?'
            );
            $upd->bind_param('ssssi', $new_username, $new_email, $new_indirizzo, $new_telefono, $uid);
            if ($upd->execute()) {
                $profile_success = 'Profilo aggiornato con successo';
                $currentUserData['username']  = $new_username;
                $currentUserData['email']     = $new_email;
                $currentUserData['indirizzo'] = $new_indirizzo;
                $currentUserData['telefono']  = $new_telefono;
            } else {
                $profile_error = 'Errore durante l\'aggiornamento';
            }
            $upd->close();
        }
    }
}

$abbonamenti = [];
$abbXML = getXML(__DIR__ . '/xml/abbonamenti.xml', 'abbonamenti');
if ($abbXML) {
    foreach ($abbXML->abbonamento as $a) {
        $attr = $a->attributes();
        $abbonamenti[] = [
            'id' => (int)$attr['id'],
            'nome' => (string)$a->tipo,
            'durata' => (string)$a->durata_mesi . ' mesi',
            'prezzo' => (string)$a->prezzo . ' ' . (string)$a->valuta
        ];
    }
}

$promos = [];
$promoXML = getXML(__DIR__ . '/xml/promo.xml', 'promotions');
if ($promoXML) {
    foreach ($promoXML->promo as $p) {
        $attr = $p->attributes();
        $rawSconto = (string)($p->percentuale ?? $p->sconto ?? '');
        $fraction = parseDiscountFraction($rawSconto);
        $applicable = promoApplicableIdsFromXmlNode($p, $abbonamenti);
        $promos[] = [
            'id' => (int)($attr['id'] ?? 0),
            'titolo' => (string)($p->titolo ?? ''),
            'codice' => (string)($p->codice ?? ''),
            'sconto' => $rawSconto,
            'fraction' => $fraction,
            'applicable' => $applicable
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subscription'])) {
    if (!$is_admin) {
        $_SESSION['add_error'] = 'Azione non consentita';
        header('Location: profilo.php');
        exit;
    }

    $uname       = trim($_POST['username'] ?? '');
    $pass        = $_POST['password'] ?? '';
    $email       = trim($_POST['email'] ?? '');
    $id_abbon    = intval($_POST['id_abbonamento'] ?? 0);
    $id_promo    = intval($_POST['id_promo'] ?? 0);
    $data_inizio = $_POST['data_inizio'] ?? '';

    if ($uname === '' || $pass === '' || $email === '' || !$id_abbon || $data_inizio === '') {
        $_SESSION['add_error'] = 'Compila tutti i campi obbligatori';
        header('Location: profilo.php');
        exit;
    }

    if ($id_promo > 0) {
        $foundPromo = null;
        foreach ($promoXML->promo as $p) {
            $attr = $p->attributes();
            $pid = (int)($attr['id'] ?? 0);
            if ($pid === $id_promo) {
                $foundPromo = $p;
                break;
            }
        }
        if ($foundPromo) {
            $applicable = promoApplicableIdsFromXmlNode($foundPromo, $abbonamenti);
            if (!empty($applicable) && !in_array($id_abbon, $applicable, true)) {
                $_SESSION['add_error'] = 'La promo selezionata non è applicabile a questo tipo di abbonamento.';
                header('Location: profilo.php');
                exit;
            }
        } else {
            $_SESSION['add_error'] = 'Promo non trovata.';
            header('Location: profilo.php');
            exit;
        }
    }

    $uid = null;
    $stmt = $conn->prepare("SELECT id_user FROM users WHERE username = ?");
    $stmt->bind_param("s", $uname);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($uid);
        $stmt->fetch();
    }
    $stmt->close();

    if (!$uid) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $ins = $conn->prepare("INSERT INTO users (username, password, email, is_admin) VALUES (?, ?, ?, 0)");
        $ins->bind_param("sss", $uname, $hash, $email);
        if (!$ins->execute()) {
            $_SESSION['add_error'] = 'Errore creazione utente: ' . $conn->error;
            header('Location: profilo.php');
            exit;
        }
        $uid = $ins->insert_id;
        $ins->close();
    }

    $abbonamentiPath = __DIR__ . '/xml/abbonamenti.xml';
    $abbonamentiXML = getXML($abbonamentiPath, 'abbonamenti');
    $found = false;
    $prezzo_base = '';
    $valuta = '';
    $durata = 0;
    if ($abbonamentiXML) {
        foreach ($abbonamentiXML->abbonamento as $abb) {
            $attr = $abb->attributes();
            if ((int)$attr['id'] === $id_abbon) {
                $prezzo_base = (string)$abb->prezzo_base !== '' ? (string)$abb->prezzo_base : (string)$abb->prezzo;
                $valuta = (string)$abb->valuta;
                $durata = (int)$abb->durata_mesi;
                $found = true;
                break;
            }
        }
    }
    if (!$found) {
        $_SESSION['add_error'] = 'Tipo abbonamento non valido.';
        header('Location: profilo.php');
        exit;
    }

    $promoDiscount = 0.0;
    if ($id_promo > 0 && $promoXML) {
        foreach ($promoXML->promo as $p) {
            $attr = $p->attributes();
            if ((int)$attr['id'] === $id_promo) {
                $rawSconto = (string)($foundPromo->percentuale ?? $foundPromo->sconto ?? '0');
                $promoDiscount = parseDiscountFraction($rawSconto);
                break;
            }
        }
    }

    $baseFloat = floatval(str_replace(',', '.', ($prezzo_base !== '' ? $prezzo_base : '0')));
    $computedPrice = $baseFloat * (1.0 - $promoDiscount);
    $computedPrice = number_format(max(0, $computedPrice), 2, '.', '');

    $data_scadenza = $durata > 0 ? date('Y-m-d', strtotime($data_inizio . " +{$durata} month")) : date('Y-m-d', strtotime($data_inizio . " +7 day"));

    $userAbbPath = __DIR__ . '/xml/user_abbonamenti.xml';
    $userAbbXML = getXML($userAbbPath, 'user_abbonamenti');
    if (!$userAbbXML) {
        $userAbbXML = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><user_abbonamenti></user_abbonamenti>');
    }

    $maxNum = 0;
    foreach ($userAbbXML->abbonamento_utente as $node) {
        $attrId = (string)$node['id'];
        if ($attrId === '') {
            $attrObj = $node->attributes();
            $attrId = $attrObj && isset($attrObj['id']) ? (string)$attrObj['id'] : '';
        }
        if (preg_match('/(\d+)$/', $attrId, $m)) {
            $num = (int)$m[1];
            if ($num > $maxNum) $maxNum = $num;
        }
    }
    $newAbbNum = $maxNum + 1;
    $newAbbId = 'a' . $newAbbNum;

    $newAbb = $userAbbXML->addChild('abbonamento_utente');
    $newAbb->addAttribute('id', $newAbbId);
    $newAbb->addChild('id_user', (string)$uid);
    $newAbb->addChild('id_abbonamento', (string)$id_abbon);
    $newAbb->addChild('data_inizio', $data_inizio);
    $newAbb->addChild('data_scadenza', $data_scadenza);
    $newAbb->addChild('stato', 'attivo');
    $newAbb->addChild('prezzo', $computedPrice);
    $newAbb->addChild('valuta', $valuta ?: 'EUR');
    $newAbb->addChild('prezzo_base', number_format($baseFloat, 2, '.', ''));

    if ($id_promo > 0) {
        $newAbb->addChild('id_promo', (string)$id_promo);
    }

    if ($userAbbXML->asXML($userAbbPath) === false) {
        $_SESSION['add_error'] = "Errore salvataggio abbonamento. Controlla i permessi della cartella /xml";
    } else {
        $_SESSION['add_success'] = 'Abbonamento aggiunto con successo.';
    }

    header('Location: profilo.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_subscription'])) {
    if ($is_admin) {
        $_SESSION['add_error'] = 'Azione non consentita per admin';
        header('Location: profilo.php');
        exit;
    }

    $req_abbon    = intval($_POST['id_abbonamento'] ?? 0);
    $req_promo    = intval($_POST['id_promo'] ?? 0);
    $req_data_in  = $_POST['data_inizio'] ?? '';

    if (!$req_abbon || $req_data_in === '') {
        $_SESSION['add_error'] = 'Compila tutti i campi per la richiesta di abbonamento.';
        header('Location: profilo.php');
        exit;
    }

    $xmlSubsCheck = getXML(__DIR__ . '/xml/user_abbonamenti.xml', 'user_abbonamenti');
    if ($xmlSubsCheck) {
        foreach ($xmlSubsCheck->abbonamento_utente as $n) {
            $uid = isset($n->id_user) ? (int)$n->id_user : 0;
            $rawState = isset($n->stato) ? (string)$n->stato : '';
            if (in_array($rawState, ['in_attesa', 'rifiutato', 'annullato'])) {
                $computedState = $rawState;
            } else {
                $startNode = isset($n->data_inizio) ? (string)$n->data_inizio : '';
                $endNode   = isset($n->data_scadenza) ? (string)$n->data_scadenza : '';
                $computedState = getSubscriptionStatus($startNode, $endNode);
            }

            if ($uid === $id_user && in_array($computedState, ['attivo', 'in_attesa', 'in_scadenza'])) {
                $_SESSION['add_error'] = 'Hai già un abbonamento attivo o una richiesta in attesa.';
                header('Location: profilo.php');
                exit;
            }
        }
    }

    $abbonamentiPath = __DIR__ . '/xml/abbonamenti.xml';
    $abbonamentiXML = getXML($abbonamentiPath, 'abbonamenti');
    $found = false;
    $prezzo_base = '';
    $valuta = '';
    $durata = 0;
    if ($abbonamentiXML) {
        foreach ($abbonamentiXML->abbonamento as $abb) {
            $attr = $abb->attributes();
            if ((int)$attr['id'] === $req_abbon) {
                $prezzo_base = (string)$abb->prezzo_base !== '' ? (string)$abb->prezzo_base : (string)$abb->prezzo;
                $valuta = (string)$abb->valuta;
                $durata = (int)$abb->durata_mesi;
                $found = true;
                break;
            }
        }
    }

    if (!$found) {
        $_SESSION['add_error'] = 'Tipo abbonamento non valido.';
        header('Location: profilo.php');
        exit;
    }

    $promoDiscount = 0.0;
    if ($req_promo > 0 && $promoXML) {
        $promoNodeFound = null;
        foreach ($promoXML->promo as $pnode) {
            $attr = $pnode->attributes();
            if ((int)($attr['id'] ?? 0) === $req_promo) {
                $promoNodeFound = $pnode;
                break;
            }
        }
        if ($promoNodeFound) {
            $applicable = promoApplicableIdsFromXmlNode($promoNodeFound, $abbonamenti);
            if (!empty($applicable) && !in_array($req_abbon, $applicable, true)) {
                $_SESSION['add_error'] = 'La promo scelta non è applicabile a questo tipo di abbonamento.';
                header('Location: profilo.php');
                exit;
            }
            $rawSconto = (string)($promoNodeFound->percentuale ?? $promoNodeFound->sconto ?? '0');
            $promoDiscount = parseDiscountFraction($rawSconto);
        } else {
            $_SESSION['add_error'] = 'Promo non valida.';
            header('Location: profilo.php');
            exit;
        }
    }

    $baseFloat = floatval(str_replace(',', '.', ($prezzo_base !== '' ? $prezzo_base : '0')));
    $computedPrice = $baseFloat * (1.0 - $promoDiscount);
    $computedPrice = number_format(max(0, $computedPrice), 2, '.', '');

    $data_scadenza = $durata > 0 ? date('Y-m-d', strtotime($req_data_in . " +{$durata} month")) : date('Y-m-d', strtotime($req_data_in . " +7 day"));

    $userAbbPath = __DIR__ . '/xml/user_abbonamenti.xml';
    $userAbbXML = getXML($userAbbPath, 'user_abbonamenti');
    if (!$userAbbXML) {
        $userAbbXML = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><user_abbonamenti></user_abbonamenti>');
    }

    $maxNum = 0;
    foreach ($userAbbXML->abbonamento_utente as $node) {
        $attrId = (string)$node['id'];
        if ($attrId === '') {
            $attrObj = $node->attributes();
            $attrId = $attrObj && isset($attrObj['id']) ? (string)$attrObj['id'] : '';
        }
        if (preg_match('/(\d+)$/', $attrId, $m)) {
            $num = (int)$m[1];
            if ($num > $maxNum) $maxNum = $num;
        }
    }
    $newAbbNum = $maxNum + 1;
    $newAbbId = 'a' . $newAbbNum;

    $newAbb = $userAbbXML->addChild('abbonamento_utente');
    $newAbb->addAttribute('id', $newAbbId);
    $newAbb->addChild('id_user', (string)$id_user);
    $newAbb->addChild('id_abbonamento', (string)$req_abbon);
    $newAbb->addChild('data_inizio', $req_data_in);
    $newAbb->addChild('data_scadenza', $data_scadenza);
    $newAbb->addChild('stato', 'in_attesa'); 
    $newAbb->addChild('prezzo', $computedPrice);
    $newAbb->addChild('valuta', $valuta ?: 'EUR');
    $newAbb->addChild('prezzo_base', number_format($baseFloat, 2, '.', ''));

    if ($req_promo > 0) {
        $newAbb->addChild('id_promo', (string)$req_promo);
    }

    if ($userAbbXML->asXML($userAbbPath) === false) {
        $_SESSION['add_error'] = "Errore salvataggio richiesta. Controlla i permessi della cartella /xml";
    } else {
        $_SESSION['add_success'] = 'Richiesta abbonamento inviata. In attesa di conferma amministratore.';
    }

    header('Location: profilo.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subscription_type'])) {
    if (!$is_admin) {
        $_SESSION['add_type_error'] = 'Azione non consentita';
        header('Location: profilo.php');
        exit;
    }

    $tipo = trim($_POST['tipo'] ?? '');
    $durata_mesi = intval($_POST['durata_mesi'] ?? 0);
    $prezzo = floatval(str_replace(',', '.', $_POST['prezzo'] ?? '0'));
    
    if (empty($tipo) || $durata_mesi <= 0 || $prezzo <= 0) {
        $_SESSION['add_type_error'] = 'Compila tutti i campi correttamente';
        header('Location: profilo.php');
        exit;
    }

    $abbonamentiPath = __DIR__ . '/xml/abbonamenti.xml';
    $abbonamentiXML = getXML($abbonamentiPath, 'abbonamenti');
    
    if (!$abbonamentiXML) {
        $_SESSION['add_type_error'] = 'Errore nel caricamento dei dati';
        header('Location: profilo.php');
        exit;
    }

    $maxId = 0;
    foreach ($abbonamentiXML->abbonamento as $abb) {
        $id = (int)$abb['id'];
        if ($id > $maxId) $maxId = $id;
    }
    $newId = $maxId + 1;


    $newAbb = $abbonamentiXML->addChild('abbonamento');
    $newAbb->addAttribute('id', $newId);
    $newAbb->addChild('tipo', $tipo);
    $newAbb->addChild('durata_mesi', $durata_mesi);
    $newAbb->addChild('prezzo', number_format($prezzo, 2, '.', ''));
    $newAbb->addChild('valuta', 'EUR');

    if ($abbonamentiXML->asXML($abbonamentiPath) === false) {
        $_SESSION['add_type_error'] = "Errore salvataggio tipo abbonamento. Controlla i permessi della cartella /xml";
    } else {
        $_SESSION['add_type_success'] = 'Tipo abbonamento aggiunto con successo.';
    }

    header('Location: profilo.php');
    exit;
}

$subs     = [];
$xmlSubs  = getXML(__DIR__ . '/xml/user_abbonamenti.xml', 'user_abbonamenti');
if ($xmlSubs) {
    foreach ($xmlSubs->abbonamento_utente as $a) {
        $uid   = isset($a->id_user) ? (int)$a->id_user : 0;
        
        $start = (string)($a->data_inizio ?? '');
        $end   = (string)($a->data_scadenza ?? '');
        $today = date('Y-m-d');

        $rawState = isset($a->stato) ? (string)$a->stato : '';
        if (in_array($rawState, ['in_attesa', 'rifiutato', 'annullato'])) {
            $status = $rawState;
        } else {
            $status = getSubscriptionStatus($start, $end, $today);
        }

        $id_abbon = isset($a->id_abbonamento) ? (int)$a->id_abbonamento : 0;
        $id_promo = isset($a->id_promo) ? (int)$a->id_promo : 0;
        $xmlId    = (string)$a['id'];

        $subs[] = [
            'xml_id'              => $xmlId,
            'id_user'             => $uid,
            'data_inizio'         => $start,
            'data_scadenza'       => $end,
            'stato'               => $status,
            'id_abbonamento'      => $id_abbon,
            'id_promo'            => $id_promo
        ];
    }
}

$userMap = [];
if ($subs) {
    $ids = array_unique(array_map('intval', array_column($subs, 'id_user')));
    $ids = array_filter($ids, function($v){ return $v > 0; });
    if (!empty($ids)) {
        $in = implode(',', $ids); 
        $sql = "SELECT id_user, username FROM users WHERE id_user IN ($in)";
        $res = $conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $userMap[$r['id_user']] = $r['username'];
            }
        }
    }
}

$has_active_subscription = false;
$currentSubscription     = null;
if (!$is_admin) {
    $userSubs = array_values(array_filter($subs, function($s) use ($id_user) {
        return $s['id_user'] == $id_user;
    }));

    if (!empty($userSubs)) {
        $latest = null;
        $latestNum = -1;
        foreach ($userSubs as $s) {
            $xmlId = (string)$s['xml_id'];
            if (preg_match('/(\d+)$/', $xmlId, $m)) {
                $num = (int)$m[1];
                if ($num > $latestNum) {
                    $latestNum = $num;
                    $latest = $s;
                }
            } else {
                if ($latest === null) $latest = $s;
                else {
                    $d1 = $s['data_inizio'] ?? '';
                    $d2 = $latest['data_inizio'] ?? '';
                    if ($d1 && $d2 && strtotime($d1) > strtotime($d2)) {
                        $latest = $s;
                    } elseif ($d1 && empty($d2)) {
                        $latest = $s;
                    }
                }
            }
        }

        if ($latest === null) {
            usort($userSubs, function($a, $b) {
                $da = isset($a['data_inizio']) ? strtotime($a['data_inizio']) : 0;
                $db = isset($b['data_inizio']) ? strtotime($b['data_inizio']) : 0;
                return $db <=> $da; 
            });
            $latest = $userSubs[0];
        }

        $currentSubscription = $latest;

        if ($currentSubscription) {
            if (!in_array($currentSubscription['stato'], ['in_attesa', 'rifiutato', 'annullato'])) {
                $currentSubscription['stato'] = getSubscriptionStatus($currentSubscription['data_inizio'], $currentSubscription['data_scadenza']);
            }
            $has_active_subscription = in_array($currentSubscription['stato'], ['attivo', 'in_scadenza']);
        }
    }
}

$can_request = false;
if (!$is_admin) {
    if (!$currentSubscription || !in_array($currentSubscription['stato'], ['attivo', 'in_attesa', 'in_scadenza'])) {
        $can_request = true;
    }
}

$course_ratings = [];
$user_reputations = [];
$forum_heatmap = [];
$promo_usage = [];
$satisfaction_index = 0;

if ($is_admin) {
    $corsiBaseXML    = getXML(__DIR__ . '/xml/corsi_base.xml', 'corsi_base');
    $feedbackXML     = getXML(__DIR__ . '/xml/feedback.xml', 'feedback');
    $lezioniXML      = getXML(__DIR__ . '/xml/lezioni.xml', 'lezioni');
    $reputazioneXML  = getXML(__DIR__ . '/xml/reputazione.xml');
    $forumXML        = getXML(__DIR__ . '/xml/forum.xml');
    $promoXML        = getXML(__DIR__ . '/xml/promo.xml');
    $userAbbXML      = getXML(__DIR__ . '/xml/user_abbonamenti.xml');

    if ($feedbackXML && $lezioniXML && $corsiBaseXML) {
        $lessonToCourse = [];
        foreach ($lezioniXML->lezione as $lez) {
            $lid = (int)$lez['id'];
            $cid = isset($lez->id_corso_base) ? (int)$lez->id_corso_base : 0;
            if ($lid > 0 && $cid > 0) $lessonToCourse[$lid] = $cid;
        }

        $courseBaseNames = [];
        foreach ($corsiBaseXML->corso_base as $cb) {
            $cid = (int)$cb['id'];
            $courseBaseNames[$cid] = (string)$cb->nome;
        }

        $ratingsAgg = [];
        foreach ($feedbackXML->valutazione_corso as $val) {
            $lid = null;
            if (isset($val->id_lezione)) {
                $lid = (int)$val->id_lezione;
            } else {
                $attr = $val->attributes();
                if ($attr && isset($attr['id_lezione'])) {
                    $lid = (int)$attr['id_lezione'];
                }
            }
            if (!$lid) continue;
            if (!isset($lessonToCourse[$lid])) continue;
            $cid = $lessonToCourse[$lid];
            $vote = isset($val->voto) ? (int)$val->voto : 0;
            if (!isset($ratingsAgg[$cid])) $ratingsAgg[$cid] = ['sum' => 0, 'count' => 0];
            $ratingsAgg[$cid]['sum'] += $vote;
            $ratingsAgg[$cid]['count'] += 1;
        }

        foreach ($ratingsAgg as $cid => $agg) {
            $avg = $agg['count'] > 0 ? round($agg['sum'] / $agg['count'], 2) : 0;
            $title = $courseBaseNames[$cid] ?? "Corso #{$cid}";
            $course_ratings[] = [
                'corso' => $title,
                'media' => $avg,
                'valutazioni' => $agg['count']
            ];
        }

        usort($course_ratings, function($a, $b) {
            if ($a['media'] === $b['media']) return $b['valutazioni'] - $a['valutazioni'];
            return $b['media'] <=> $a['media'];
        });
    }

    $user_reputations = [];
    if ($reputazioneXML) {
        foreach ($reputazioneXML->utente as $utente) {
            $uid = (int)$utente['id'];
            $username = (string)$utente->username;
            $reputazione = floatval($utente->punteggio);
            
            $user_reputations[] = [
                'username' => $username,
                'reputazione' => $reputazione
            ];
        }
    }

    if ($forumXML) {
        $threadActivity = [];
        
        foreach ($forumXML->thread as $thread) {
            $titolo = (string)$thread->titolo;
            $commentCount = 0;
            if (isset($thread->commenti) && isset($thread->commenti->commento)) {
                $commentCount = count($thread->commenti->commento);
            }
            
            $threadActivity[] = [
                'titolo' => $titolo,
                'commenti' => $commentCount
            ];
        }
        
        usort($threadActivity, function($a, $b) {
            return $b['commenti'] - $a['commenti'];
        });
        
        $forum_heatmap = array_slice($threadActivity, 0, 5);
    }

    if ($promoXML && $userAbbXML) {
        $promoCounts = [];
        
        foreach ($promoXML->promo as $promo) {
            $promoId = (int)$promo['id'];
            $titolo = (string)$promo->titolo;
            $promoCounts[$promoId] = [
                'titolo' => $titolo,
                'count' => 0
            ];
        }
        
        foreach ($userAbbXML->abbonamento_utente as $abb) {
            if (isset($abb->id_promo)) {
                $promoId = (int)$abb->id_promo;
                if (isset($promoCounts[$promoId])) {
                    $promoCounts[$promoId]['count']++;
                }
            }
        }
        
        $promo_usage = array_values($promoCounts);
    }

    $totalRatings = 0;
    $ratingCount = 0;
    $totalReputation = 0;
    $userCount = 0;
    $totalComments = 0;
    
    foreach ($course_ratings as $rating) {
        $totalRatings += $rating['media'];
        $ratingCount++;
    }
    $avgCourseRating = $ratingCount > 0 ? $totalRatings / $ratingCount : 0;
    
    foreach ($user_reputations as $user) {
        $totalReputation += $user['reputazione'];
        $userCount++;
    }
    $avgReputation = $userCount > 0 ? $totalReputation / $userCount : 0;
    
    foreach ($forum_heatmap as $thread) {
        $totalComments += $thread['commenti'];
    }
    
    $satisfaction_index = round(
        ($avgCourseRating * 0.4) + 
        ($avgReputation * 0.3) + 
        (min($totalComments, 100) * 0.3)
    );
}

$unread_count     = 0;
$user_chat_content= '';
$has_unread       = false;
$chatXML          = getXML(__DIR__ . '/xml/private_chat.xml');

if ($is_admin) {
    $messages = [];
    
    if ($chatXML) {
        foreach ($chatXML->messaggio as $msg) {
            $attr   = $msg->attributes();
            $mitt   = (string)$attr['mittente'];
            $dest   = (string)$attr['destinatario'];
            $read   = isset($attr['risposta']) ? (int)$attr['risposta'] : 0; 
            $sollecito_inviato = (string)$attr['sollecito_inviato']; 
            $date   = (string)$attr['data'];
            
            $isFromUser = ($dest === 'admin' || $dest === 'admin');
            $isToUser = ($mitt === 'admin' || $mitt === 'admin');
            
            if ($isFromUser || $isToUser) {
                $ts   = date('d/m H:i', strtotime($date));
                $who  = ($mitt === 'admin' || $mitt === 'admin') ? 'Staff' : h($mitt);
                $cls  = ($isToUser && $read === 0) ? 'unread' : '';
                
                $msgHtml = 
                   "<div class=\"widget-item {$cls}\">"
                  ."<strong>{$who}</strong>"
                  ."<small>".h($ts)."</small>"
                  ."<p>".nl2br(h((string)$msg->testo))."</p>"
                  ."</div>";
                
                $messages[] = [
                    'timestamp' => strtotime($date),
                    'html' => $msgHtml
                ];
                
                if ($isToUser && $read === 0) {
                    $unread_count++;
                }
            }
        }
    }
    
    usort($messages, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });
    
    foreach ($messages as $m) {
        $user_chat_content .= $m['html'];
    }
    
    $has_unread = $unread_count > 0;
} 

elseif (!$is_admin && $has_active_subscription && $chatXML) {
    $messages = [];
    
    foreach ($chatXML->messaggio as $msg) {
        $attr   = $msg->attributes();
        $mitt   = (string)$attr['mittente'];
        $dest   = (string)$attr['destinatario'];
        $read   = isset($attr['risposta']) ? (int)$attr['risposta'] : 0; 
        $sollecito_inviato = (string)$attr['sollecito_inviato']; 
        $date   = (string)$attr['data'];
        
        $isForMe  = ($mitt === 'admin') 
                   && ($dest === (string)$id_user || $dest === $currentUserData['username']);
        
        $isFromMe = (($mitt === (string)$id_user || $mitt === $currentUserData['username']) 
                   && ($dest === 'admin'));
        
        if (!($isForMe || $isFromMe)) continue;
        
        $ts   = date('d/m H:i', strtotime($date));
        $who  = $isFromMe ? 'Tu' : 'Staff';
        $cls  = ($isForMe && $read === 0) ? 'unread' : '';
        
        $msgHtml = 
           "<div class=\"widget-item {$cls}\">"
          ."<strong>{$who}</strong>"
          ."<small>".h($ts)."</small>"
          ."<p>".nl2br(h((string)$msg->testo))."</p>"
          ."</div>";
        
        $messages[] = [
            'timestamp' => strtotime($date),
            'html' => $msgHtml
        ];
        
        if ($isForMe && $read === 0) {
            $unread_count++;
        }
    }
    
    usort($messages, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });
    
    foreach ($messages as $m) {
        $user_chat_content .= $m['html'];
    }
    
    $has_unread = $unread_count > 0;
}

$unread = [];
if ($is_admin && $chatXML) {
    foreach ($chatXML->messaggio as $msg) {
        $attr = $msg->attributes();
        $mitt = (string)$attr['mittente'];
        $dest = (string)$attr['destinatario'];
        $read = isset($attr['risposta']) ? (int)$attr['risposta'] : 0;
        $sollecito_inviato = (string)$attr['sollecito_inviato']; 
        
        if ($read === 0 && $dest === 'admin') {
            $uid = null;
            $stmt = $conn->prepare("SELECT id_user FROM users WHERE username = ?");
            $stmt->bind_param("s", $mitt);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $stmt->bind_result($uid);
                $stmt->fetch();
                $unread[$uid] = ($unread[$uid] ?? 0) + 1;
            }
            $stmt->close();
        }
    }
}

$allUsers = [];
if ($is_admin) {
    $stmt = $conn->prepare("SELECT id_user, username FROM users");
    $stmt->execute();
    $res = $stmt->get_result();
    while ($user = $res->fetch_assoc()) {
        $allUsers[] = $user;
    }
    $stmt->close();
}

$conn->close();

$tipoAbbonamento = 'Nessun abbonamento';
if (!$is_admin && $currentSubscription) {
    foreach ($abbonamenti as $abb) {
        if ($abb['id'] == $currentSubscription['id_abbonamento']) {
            $tipoAbbonamento = $abb['nome'];
            break;
        }
    }
}

$promos_for_js = [];
foreach ($promos as $p) {
    $promos_for_js[] = [
        'id' => $p['id'],
        'titolo' => $p['titolo'],
        'codice' => $p['codice'],
        'sconto' => $p['sconto'],
        'fraction' => $p['fraction'],
        'applicable' => $p['applicable'] 
    ];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profilo Utente - PALESTRW</title>

  <link rel="stylesheet" href="style/style_profilo.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <?php if ($force_password_change): ?>
    <div class="modal-overlay"></div>
  <?php endif; ?>

  <div id="header" class="main-header">
    <div class="logo">PALESTRW</div>
    <ul class="main-menu">
      <?php if (!$id_user): ?>
        <li><a href="login.php"><img src="icon/area_riservata.png" class="icon">Area Riservata</a></li>
        <li><a href="home_page.php"><img src="icon/home.png" class="icon">Home</a></li>
        <li><a href="promo.php"><img src="icon/promo.png" class="icon">Promo</a></li>
        <li><a href="corsi.php"><img src="icon/corsi.png" class="icon">Corsi</a></li>
        <li><a href="Chi_Siamo.php"><img src="icon/chi_siamo.png" class="icon">Chi Siamo</a></li>
        <li><a href="faq.php"><img src="icon/faq.png" class="icon">FAQ</a></li>
      <?php else: ?>
        <li><a href="home_page.php"><img src="icon/home.png" class="icon">Home</a></li>
        <li><a href="promo.php"><img src="icon/promo.png" class="icon">Promo</a></li>
        <li><a href="corsi.php"><img src="icon/corsi.png" class="icon">Corsi</a></li>
        <li><a href="Chi_Siamo.php"><img src="icon/chi_siamo.png" class="icon">Chi Siamo</a></li>
        <li><a href="faq.php"><img src="icon/faq.png" class="icon">FAQ</a></li>
        <li><a href="logout.php"><img src="icon/logout.png" class="icon">Logout</a></li>
      <?php endif; ?>
    </ul>
  </div>

  <main class="main-content">

    <?php if($profile_error): ?>
      <div class="alert alert-error"><?= h($profile_error) ?></div>
    <?php elseif($profile_success): ?>
      <div class="alert alert-success"><?= h($profile_success) ?></div>
    <?php endif; ?>

    <?php if($add_error): ?>
      <div class="alert alert-error"><?= h($add_error) ?></div>
    <?php elseif($add_success): ?>
      <div class="alert alert-success"><?= h($add_success) ?></div>
    <?php endif; ?>

    <?php if($add_type_error): ?>
      <div class="alert alert-error"><?= h($add_type_error) ?></div>
    <?php elseif($add_type_success): ?>
      <div class="alert alert-success"><?= h($add_type_success) ?></div>
    <?php endif; ?>

    <div class="profile-header">
      <h1>Il Tuo Profilo</h1>
      <div class="profile-actions">
        <button id="btn-change-pw" class="btn-action"><i class="fas fa-key"></i> Cambia Password</button>
        <button id="btn-edit-profile" class="btn-action secondary"><i class="fas fa-edit"></i> Modifica Profilo</button>
        <?php if ($can_request): ?>
          <button id="btn-request-sub" class="btn-action"><i class="fas fa-plus-circle"></i> Richiedi Abbonamento</button>
        <?php endif; ?>
        <?php if ($is_admin): ?>
          <button id="btn-add-sub-type" class="btn-action"><i class="fas fa-plus-square"></i> Aggiungi Tipo Abbonamento</button>
        <?php endif; ?>
      </div>
    </div>

    <div class="profile-container">
      <div class="personal-data-section">
        <div class="info-card">
          <div class="card-header">
            <i class="fas fa-user"></i>
            <h2>Dati Personali</h2>       
          </div>
          <div class="info-grid">
            <div class="info-item"><span class="info-label">Username:</span><span class="info-value"><?= h($currentUserData['username']) ?></span></div>
            <div class="info-item"><span class="info-label">Email:</span><span class="info-value"><?= h($currentUserData['email']) ?></span></div>
            <div class="info-item"><span class="info-label">Indirizzo:</span><span class="info-value"><?= h($currentUserData['indirizzo']) ?></span></div>
            <div class="info-item"><span class="info-label">Telefono:</span><span class="info-value"><?= h($currentUserData['telefono']) ?></span></div>
            <div class="stat-card">
              <h3><i class="fas fa-star"></i> Reputazione</h3>
              <div class="stat-content">
                <div class="stars">
                  <?php 
                    $reputazione = $currentUserData['reputazione'] ?? 0;
                    $starCount = min(5, max(0, round($reputazione / 20))); 
                    
                    for ($i = 0; $i < 5; $i++) { 
                      $activeClass = $i < $starCount ? 'active' : '';
                      echo "<i class='fas fa-star $activeClass'></i>";
                    }
                  ?>
                  <span class="rep-value"><?= number_format($reputazione, 2) ?>/100</span>
                </div>
              </div>

          </div>
        </div>

        <?php if(!$is_admin && $currentSubscription): ?>
          <div class="info-card">
            <div class="card-header"><i class="fas fa-id-card"></i><h2>Abbonamento Personale</h2></div>
            <div class="info-grid">
              <div class="info-item"><span class="info-label">Tipo:</span><span class="info-value"><?= h($tipoAbbonamento) ?></span></div>
              <div class="info-item"><span class="info-label">Inizio:</span><span class="info-value"><?= h($currentSubscription['data_inizio']) ?></span></div>
              <div class="info-item"><span class="info-label">Scadenza:</span><span class="info-value"><?= h($currentSubscription['data_scadenza']) ?></span></div>
              <div class="info-item"><span class="info-label">Stato:</span>
                <?php if($currentSubscription['stato'] === 'attivo'): ?>
                  <span class="status-badge active"><i class="fas fa-check-circle"></i> Attivo</span>
                <?php elseif($currentSubscription['stato'] === 'in_scadenza'): ?>
                  <span class="status-badge expiring"><i class="fas fa-exclamation-circle"></i> In scadenza</span>
                <?php elseif($currentSubscription['stato'] === 'in_attesa'): ?>
                  <span class="badge-await"><i class="fas fa-hourglass-half"></i> In attesa di conferma</span>
                <?php elseif($currentSubscription['stato'] === 'rifiutato'): ?>
                  <span class="status-badge expired"><i class="fas fa-times-circle"></i> Rifiutato</span>
                <?php else: ?>
                  <span class="status-badge expired"><i class="fas fa-times-circle"></i> Scaduto</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>

      </div>

      <div class="right-section">
        <?php if($is_admin): ?>
          <div class="admin-section">
            <div class="admin-tabs">
              <button class="tab-btn active" data-tab="abbonamenti">Abbonamenti</button>
              <button class="tab-btn" data-tab="stats">Statistiche Avanzate</button>
            </div>

            <div id="abbonamenti-tab" class="tab-content active">
              <h2>Abbonamenti (admin)</h2>
              <div class="admin-controls">
                <div class="search-box"><i class="fas fa-search"></i><input id="search" type="search" placeholder="Cerca utenti..."></div>
                <button id="btn-add" title="Aggiungi Abbonato"><i class="fas fa-plus"></i></button>
              </div>
              <div class="table-container">
                <table id="abbonamenti-table">
                  <thead>
                    <tr><th>Utente</th><th>Inizio</th><th>Scadenza</th><th>Stato</th><th>Azioni</th><th>Chat</th></tr>
                  </thead>
                  <tbody>
                    <?php if(!empty($subs)): ?>
                      <?php
                        $latestPerUser = [];
                        foreach ($subs as $s) {
                            $uid = (int)($s['id_user'] ?? 0);
                            if ($uid <= 0) continue;
                            $xmlId = (string)($s['xml_id'] ?? '');
                            $num = 0;
                            if (preg_match('/(\d+)$/', $xmlId, $m)) $num = (int)$m[1];
                            if (!isset($latestPerUser[$uid])
                                || $num > $latestPerUser[$uid]['num']
                                || ($num === $latestPerUser[$uid]['num'] && strtotime($s['data_inizio']) > strtotime($latestPerUser[$uid]['date']))
                            ) {
                                $latestPerUser[$uid] = ['num' => $num, 'date' => $s['data_inizio'], 's' => $s];
                            }
                        }
                        $subs = array_values(array_map(function($v){ return $v['s']; }, $latestPerUser));?>
                      <?php foreach($subs as $s):
                        $u = $s['id_user'];
                        $uname = $userMap[$u] ?? 'Sconosciuto';
                      ?>
                        <tr data-userid-row="<?= h($s['xml_id']) ?>">
                          <td><?= h($uname) ?></td>
                          <td><?= h($s['data_inizio']) ?></td>
                          <td><?= h($s['data_scadenza']) ?></td>
                          <td>
                            <?php if($s['stato']==='attivo'): ?>
                              <span class="status-badge active"><i class="fas fa-check-circle"></i> Attivo</span>
                            <?php elseif($s['stato']==='in_scadenza'): ?>
                              <span class="status-badge expiring"><i class="fas fa-exclamation-circle"></i> In scadenza</span>
                            <?php elseif($s['stato']==='in_attesa'): ?>
                              <span class="badge-await"><i class="fas fa-hourglass-half"></i> In attesa</span>
                            <?php elseif($s['stato']==='rifiutato'): ?>
                              <span class="status-badge expired"><i class="fas fa-times-circle"></i> Rifiutato</span>
                            <?php else: ?>
                              <span class="status-badge expired"><i class="fas fa-times-circle"></i> Scaduto</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php if($s['stato'] === 'in_attesa'): ?>
                              <a class="btn-confirm" href="confirm_abbonamento.php?id=<?= urlencode($s['xml_id']) ?>&action=confirm" title="Conferma">CONFERMA</a>
                              <a class="btn-reject" href="confirm_abbonamento.php?id=<?= urlencode($s['xml_id']) ?>&action=reject" title="Rifiuta">RIFIUTA</a>
                            <?php else: ?>
                              <small>Nessuna azione</small>
                            <?php endif; ?>
                          </td>
                          <td class="chat-cell">
                            <?php if(in_array($s['stato'], ['scaduto', 'rifiutato', 'annullato'])): ?>
                              <span class="chat-badge disabled" title="Abbonamento scaduto, chat non disponibile" data-userid="<?= $u ?>">
                                <i class="fas fa-comments"></i>
                                <span class="unread-badge" id="badge-<?= $u ?>" <?= (isset($unread[$u])&&$unread[$u]>0)?'':'style="display:none;"' ?>>
                                  <?= isset($unread[$u])?$unread[$u]:0 ?>
                                </span>
                              </span>
                            <?php else: ?>
                              <a href="admin_chat.php?user_id=<?= $u ?>" class="chat-badge <?= isset($unread[$u])&&$unread[$u]>0?'has-unread':'' ?>" data-userid="<?= $u ?>">
                                <i class="fas fa-comments"></i>
                                <span class="unread-badge" id="badge-<?= $u ?>" <?= (isset($unread[$u])&&$unread[$u]>0)?'':'style="display:none;"' ?>>
                                  <?= isset($unread[$u])?$unread[$u]:0 ?>
                                </span>
                              </a>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr><td colspan="6"><div class="no-subscriptions"><i class="fas fa-info-circle"></i> Nessun abbonamento trovato</div></td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div id="stats-tab" class="tab-content">
              <div class="stats-container">
                <h2>Statistiche Avanzate</h2>
                <div class="stats-grid">
                  <div class="stat-card">
                    <h3><i class="fas fa-star"></i> Medie Valutazioni Corsi</h3>
                    <ul class="stat-list">
                      <?php if(!empty($course_ratings)): ?>
                        <?php foreach($course_ratings as $rating): ?>
                          <li>
                            <span><?= h($rating['corso']) ?></span>
                            <span>
                              <?= h($rating['media']) ?> 
                              <small>(<?= h($rating['valutazioni']) ?> valutazioni)</small>
                            </span>
                          </li>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <li>Nessun dato disponibile</li>
                      <?php endif; ?>
                    </ul>
                  </div>
                  
                  <div class="stat-card">
                    <h3><i class="fas fa-chart-line"></i> Reputazione Utenti</h3>
                    <ul class="stat-list">
                      <?php if(!empty($user_reputations)): ?>
                        <?php foreach($user_reputations as $user): ?>
                          <li>
                            <span><?= h($user['username']) ?></span>
                            <span><?= number_format($user['reputazione'], 2) ?>/100</span>
                          </li>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <li>Nessun dato disponibile</li>
                      <?php endif; ?>
                    </ul>
                  </div>
                  
                  <div class="stat-card">
                    <h3><i class="fas fa-fire"></i> Forum: Aree Più Attive</h3>
                    <ul class="stat-list">
                      <?php if(!empty($forum_heatmap)): ?>
                        <?php foreach($forum_heatmap as $thread): ?>
                          <li>
                            <span><?= h($thread['titolo']) ?></span>
                            <span><?= h($thread['commenti']) ?> commenti</span>
                            <div class="heatmap-bar">
                              <div class="heatmap-fill" style="width: <?= min(100, $thread['commenti'] * 5) ?>%"></div>
                            </div>
                          </li>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <li>Nessun dato disponibile</li>
                      <?php endif; ?>
                    </ul>
                  </div>
                  
                  <div class="stat-card">
                    <h3><i class="fas fa-tags"></i> Utilizzo Promozioni</h3>
                    <ul class="stat-list">
                      <?php if(!empty($promo_usage)): ?>
                        <?php foreach($promo_usage as $promo): ?>
                          <li>
                            <span><?= h($promo['titolo']) ?></span>
                            <span><?= h($promo['count']) ?> utilizzi</span>
                          </li>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <li>Nessuna promozione utilizzata</li>
                      <?php endif; ?>
                    </ul>
                  </div>
                  
                  <div class="stat-card">
                    <h3><i class="fas fa-smile"></i> Indice Soddisfazione</h3>
                    <div class="satisfaction-gauge">
                      <div class="gauge-value"><?= h($satisfaction_index) ?>/100</div>
                      <div class="gauge-label">Gradimento Generale Community</div>
                      <div class="gauge-bar">
                        <div class="gauge-fill" style="width: <?= h($satisfaction_index) ?>%"></div>
                      </div>
                      <div class="gauge-info">
                        <small>Calcolato su: valutazioni corsi, reputazione utenti e attività forum</small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        <?php elseif(!$is_admin && $has_active_subscription): ?>
          <div class="chat-container">
            <div class="chat-bubble" id="chat-bubble">
              <i class="fas fa-comments"></i>
              <?php if($has_unread): ?><span class="unread-badge"><?= $unread_count ?></span><?php endif; ?>
            </div>
            <div class="msg-widget" id="chat-widget">
              <div class="widget-header">
                <span>Chat con lo Staff</span>
                <?php if($has_unread): ?><span class="unread-indicator"><?= $unread_count ?> nuovi</span><?php endif; ?>
                <button class="close-chat"><i class="fas fa-times"></i></button>
              </div>
              <div class="widget-body" id="chat-messages">
                <?php if(empty($user_chat_content)): ?>
                  <div class="no-msg"><i class="fas fa-comment-slash"></i><p>Nessun messaggio, invia il primo!</p></div>
                <?php else: ?>
                  <?= $user_chat_content ?>
                <?php endif; ?>
              </div>
              <form class="widget-form" method="post" action="invio_mess_utente.php" id="chat-form">
                <textarea name="testo" placeholder="Scrivi un messaggio..." required></textarea>
                <button type="submit"><i class="fas fa-paper-plane"></i> Invia</button>
              </form>
            </div>
            </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <div class="modal <?= $force_password_change ? 'force-open' : '' ?>" id="modal-change-pw">
    <div class="modal-content">
      <button class="close-button">&times;</button>
      <h2><i class="fas fa-key"></i> Cambia Password</h2>
      <?php if($cp_error): ?><div class="alert alert-error"><?= h($cp_error) ?></div><?php endif; ?>
      <?php if($cp_success): ?><div class="alert alert-success"><?= h($cp_success) ?></div><?php endif; ?>
      <form method="post" action="profilo.php">
        <div class="form-group">
          <label for="current_password">Password Attuale</label>
          <div class="password-input">
            <input type="password" name="current_password" id="current_password" required <?= $force_password_change ? 'autofocus' : '' ?>>
            <i class="fas fa-eye toggle-password"></i>
          </div>
        </div>
        <div class="form-group">
          <label for="new_password">Nuova Password</label>
          <div class="password-input">
            <input type="password" name="new_password" id="new_password" required pattern="(?=.*\d)(?=.*[A-Z]).{8,}" title="Almeno 8 caratteri, 1 numero e 1 maiuscola">
            <i class="fas fa-eye toggle-password"></i>
          </div>
          <div class="password-rules">
            <ul>
              <li id="rule-length">Minimo 8 caratteri</li>
              <li id="rule-uppercase">Almeno 1 maiuscola</li>
              <li id="rule-number">Almeno 1 numero</li>
            </ul>
          </div>
        </div>
        <div class="form-group">
          <label for="confirm_password">Conferma Password</label>
          <div class="password-input">
            <input type="password" name="confirm_password" id="confirm_password" required>
            <i class="fas fa-eye toggle-password"></i>
          </div>
          <div id="password-match"></div>
        </div>
        <button type="submit" name="change_password" class="btn-submit"><i class="fas fa-sync-alt"></i> Aggiorna Password</button>
      </form>
    </div>
  </div>

  <div class="modal" id="modal-edit-profile">
    <div class="modal-content">
      <button class="close-button">&times;</button>
      <h2><i class="fas fa-user-edit"></i> Modifica Profilo</h2>
      <form method="post" action="profilo.php">
        <div class="form-group">
          <label for="edit_username">Username</label>
          <input type="text" name="username" id="edit_username" required value="<?= h($currentUserData['username']) ?>">
        </div>
        <div class="form-group">
          <label for="edit_email">Email</label>
          <input type="email" name="email" id="edit_email" required value="<?= h($currentUserData['email']) ?>">
        </div>
        <div class="form-group">
          <label for="edit_indirizzo">Indirizzo</label>
          <input type="text" name="indirizzo" id="edit_indirizzo" required value="<?= h($currentUserData['indirizzo']) ?>">
        </div>
        <div class="form-group">
          <label for="edit_telefono">Telefono</label>
          <input type="text" name="telefono" id="edit_telefono" required value="<?= h($currentUserData['telefono']) ?>">
        </div>
        <button type="submit" name="update_profile" class="btn-submit"><i class="fas fa-save"></i> Salva Modifiche</button>
      </form>
    </div>
  </div>

  <div class="modal modal-request" id="modal-request-sub">
    <div class="modal-content">
      <button class="close-button">&times;</button>
      <h2><i class="fas fa-plus-circle"></i> Richiedi Abbonamento</h2>
      <form method="post" action="profilo.php" id="form-request-sub">
        <input type="hidden" name="request_subscription" value="1">
        <div class="form-group">
          <label for="req_abbon">Tipo abbonamento</label>
          <select name="id_abbonamento" id="req_abbon" required>
            <option value="">Seleziona</option>
            <?php foreach($abbonamenti as $a): ?>
              <option value="<?= h($a['id']) ?>"><?= h($a['nome']) ?> - <?= h($a['durata']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="req_promo">Promo (opzionale)</label>
          <select name="id_promo" id="req_promo" class="promo-select">
            <option value="">Nessuna</option>
            <?php foreach($promos as $p): 
                $percent = round($p['fraction'] * 100, 2);
                $percentLabel = ($percent == (int)$percent) ? intval($percent) . '%' : $percent . '%';
                $dataApplicable = empty($p['applicable']) ? '' : implode(',', $p['applicable']);
            ?>
              <option value="<?= h($p['id']) ?>" data-applicable="<?= h($dataApplicable) ?>" data-spercent="<?= h($percentLabel) ?>">
                <?= h($p['titolo']) ?><?= $p['codice'] ? ' ('.h($p['codice']).')' : '' ?> - <?= h($percentLabel) ?>
              </option>
            <?php endforeach; ?>
          </select>
          </div>
        <div class="form-group">
          <label for="req_data_in">Data inizio</label>
          <input type="date" name="data_inizio" id="req_data_in" required value="<?= date('Y-m-d') ?>">
        </div>
        <div style="display:flex;gap:8px;">
          <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Invia richiesta</button>
          <button type="button" class="btn-action secondary close-modal">Annulla</button>
        </div>
      </form>
    </div>
  </div>

  <?php if($is_admin): ?>
    <div class="modal" id="modal-add">
      <div class="modal-content">
        <button class="close-button">&times;</button>
        <h2><i class="fas fa-id-card-alt"></i> Nuovo Abbonato</h2>
        <form method="post" action="profilo.php" id="form-add-sub">
          <div class="form-group">
            <label for="username"><i class="fas fa-user"></i> Username</label>
            <input type="text" name="username" id="username" required>
          </div>
          <div class="form-group">
            <label for="password"><i class="fas fa-lock"></i> Password</label>
            <input type="password" name="password" id="password" required>
          </div>
          <div class="form-group">
            <label for="email"><i class="fas fa-envelope"></i> Email</label>
            <input type="email" name="email" id="email" required>
          </div>
          <div class="form-group">
            <label for="id_abbonamento"><i class="fas fa-tag"></i> Tipo Abbonamento</label>
            <select name="id_abbonamento" id="id_abbonamento" required>
              <option value="">Seleziona abbonamento</option>
              <?php foreach ($abbonamenti as $abb): ?>
                <option value="<?= h($abb['id']) ?>">
                  <?= h($abb['nome']) ?> - <?= h($abb['durata']) ?> (<?= h($abb['prezzo']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="id_promo"><i class="fas fa-tags"></i> Promo (opzionale)</label>
            <select name="id_promo" id="id_promo" class="promo-select">
              <option value="">Nessuna promo</option>
              <?php if (!empty($promos)): ?>
                <?php foreach ($promos as $p): 
                    $percent = round($p['fraction'] * 100, 2);
                    $percentLabel = ($percent == (int)$percent) ? intval($percent) . '%' : $percent . '%';
                    $dataApplicable = empty($p['applicable']) ? '' : implode(',', $p['applicable']);
                ?>
                  <option value="<?= h($p['id']) ?>" data-applicable="<?= h($dataApplicable) ?>" data-spercent="<?= h($percentLabel) ?>">
                    <?= h($p['titolo']) ?><?= $p['codice'] ? ' ('.h($p['codice']).')' : '' ?> - <?= h($percentLabel) ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="data_inizio"><i class="fas fa-calendar-day"></i> Data Inizio</label>
            <input type="date" name="data_inizio" id="data_inizio" required value="<?= date('Y-m-d') ?>">
          </div>
          <button type="submit" name="add_subscription" class="btn-submit"><i class="fas fa-plus-circle"></i> Aggiungi Abbonato</button>
        </form>
      </div>
    </div>

    <div class="modal" id="modal-add-sub-type">
      <div class="modal-content">
        <button class="close-button">&times;</button>
        <h2><i class="fas fa-plus-square"></i> Aggiungi Tipo Abbonamento</h2>
        <form method="post" action="profilo.php" id="form-add-sub-type">
          <div class="form-group">
            <label for="tipo"><i class="fas fa-tag"></i> Tipo Abbonamento</label>
            <input type="text" name="tipo" id="tipo" required placeholder="Es. Mensile, Trimestrale, Annuale">
          </div>
          <div class="form-group">
            <label for="durata_mesi"><i class="fas fa-calendar-alt"></i> Durata (mesi)</label>
            <input type="number" name="durata_mesi" id="durata_mesi" min="1" required placeholder="Es. 1, 3, 12">
          </div>
          <div class="form-group">
            <label for="prezzo"><i class="fas fa-euro-sign"></i> Prezzo</label>
            <input type="number" name="prezzo" id="prezzo" step="0.01" min="0" required placeholder="Es. 30.00">
          </div>
          <button type="submit" name="add_subscription_type" class="btn-submit"><i class="fas fa-plus-circle"></i> Aggiungi Tipo</button>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <script>
    window.PROMOS = <?= json_encode($promos_for_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    document.addEventListener('DOMContentLoaded', () => {
      const modals = {
        'btn-change-pw': 'modal-change-pw',
        'btn-edit-profile': 'modal-edit-profile',
        'btn-add': 'modal-add',
        'btn-request-sub': 'modal-request-sub',
        'btn-add-sub-type': 'modal-add-sub-type'
      };
      Object.entries(modals).forEach(([btnId, modalId]) => {
        const btn = document.getElementById(btnId);
        const modal = document.getElementById(modalId);
        if (btn && modal) {
          btn.addEventListener('click', e => { e.preventDefault(); modal.classList.add('open'); });
          const close = modal.querySelector('.close-button');
          if (close) close.addEventListener('click', () => modal.classList.remove('open'));
          const canc = modal.querySelector('.close-modal');
          if (canc) canc.addEventListener('click', () => modal.classList.remove('open'));
        }
      });

      const searchInput = document.getElementById('search');
      if (searchInput) {
        searchInput.addEventListener('input', function() {
          const term = this.value.toLowerCase();
          document.querySelectorAll('#abbonamenti-table tbody tr').forEach(row => {
            row.style.display = row.cells[0].textContent.toLowerCase().includes(term) ? '' : 'none';
          });
        });
      }

      document.querySelectorAll('.toggle-password').forEach(icon => {
        icon.addEventListener('click', () => {
          const input = icon.previousElementSibling;
          input.type = input.type === 'password' ? 'text' : 'password';
          icon.classList.toggle('fa-eye');
          icon.classList.toggle('fa-eye-slash');
        });
      });

      const newPassword = document.getElementById('new_password');
      const confirmPassword = document.getElementById('confirm_password');
      if (newPassword && confirmPassword) {
        [newPassword, confirmPassword].forEach(inp => inp.addEventListener('input', () => {
          const pwd = newPassword.value, conf = confirmPassword.value;
          document.getElementById('rule-length').style.color = pwd.length >= 8 ? '#28a745' : '#dc3545';
          document.getElementById('rule-uppercase').style.color = /[A-Z]/.test(pwd) ? '#28a745' : '#dc3545';
          document.getElementById('rule-number').style.color = /\d/.test(pwd) ? '#28a745' : '#dc3545';
          const match = document.getElementById('password-match');
          if (conf && pwd !== conf) { match.textContent='Le password non coincidono'; match.style.color='#dc3545'; }
          else if (conf && pwd === conf) { match.textContent='Le password coincidono'; match.style.color='#28a745'; }
          else match.textContent='';
        }));
      }

      function filterPromoOptionsFor(selectPromoEl, abbonId) {
        if (!selectPromoEl) return;
        const opts = Array.from(selectPromoEl.querySelectorAll('option'));
        opts.forEach(opt => {
          if (!opt.value) {
            opt.style.display = '';
            return;
          }
          const applicable = opt.getAttribute('data-applicable') || '';
          if (applicable === '') {
            opt.style.display = '';
          } else {
            const ids = applicable.split(',').map(s => s.trim()).filter(s => s !== '');
            if (ids.indexOf(String(abbonId)) !== -1) {
              opt.style.display = '';
            } else {
              opt.style.display = 'none';
              if (opt.selected) selectPromoEl.value = '';
            }
          }
        });
      }

      const reqAbbon = document.getElementById('req_abbon');
      const reqPromo = document.getElementById('req_promo');
      if (reqAbbon && reqPromo) {
        reqAbbon.addEventListener('change', () => {
          const aid = parseInt(reqAbbon.value || '0', 10);
          filterPromoOptionsFor(reqPromo, aid);
        });
        reqAbbon.addEventListener('focus', () => {
          const aid = parseInt(reqAbbon.value || '0', 10);
          filterPromoOptionsFor(reqPromo, aid);
        });
      }

      const adminAbbon = document.getElementById('id_abbonamento');
      const adminPromo  = document.getElementById('id_promo');
      if (adminAbbon && adminPromo) {
        adminAbbon.addEventListener('change', () => {
          const aid = parseInt(adminAbbon.value || '0', 10);
          filterPromoOptionsFor(adminPromo, aid);
        });
        adminAbbon.addEventListener('focus', () => {
          const aid = parseInt(adminAbbon.value || '0', 10);
          filterPromoOptionsFor(adminPromo, aid);
        });
      }

      const isAdmin = <?= $is_admin ? 'true' : 'false' ?>;
      const chatBubble = document.getElementById('chat-bubble');
      const chatWidget = document.getElementById('chat-widget');
      const chatMessages = document.getElementById('chat-messages');
      const chatForm = document.getElementById('chat-form');

      function markUserMessagesRead() {
        return fetch('mark_messages_read.php', { method: 'GET', cache: 'no-store' })
          .then(res => res.json())
          .catch(() => ({ ok: false }));
      }

      if (chatBubble && chatWidget) {
        chatBubble.addEventListener('click', () => {
          markUserMessagesRead().then(resp => {
            const badge = chatBubble.querySelector('.unread-badge');
            if (badge) badge.style.display = 'none';
            const ind = chatWidget.querySelector('.unread-indicator');
            if (ind) ind.style.display = 'none';
          }).catch(()=>{});
          
          chatWidget.classList.toggle('active');
          chatMessages && (chatMessages.scrollTop = chatMessages.scrollHeight);
          localStorage.setItem('chatOpen', chatWidget.classList.contains('active') ? '1' : '0');
        });

        const closeBtn = chatWidget.querySelector('.close-chat');
        if (closeBtn) closeBtn.addEventListener('click', () => {
          chatWidget.classList.remove('active');
          localStorage.removeItem('chatOpen');
        });
      }

      if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
      if (chatForm) chatForm.addEventListener('submit', () => {
        localStorage.setItem('chatScrollPosition', chatMessages.scrollTop);
        localStorage.setItem('chatOpen', '1');
      });
      window.addEventListener('load', () => {
        const pos = localStorage.getItem('chatScrollPosition');
        if (chatMessages && pos) { chatMessages.scrollTop = parseInt(pos,10); localStorage.removeItem('chatScrollPosition'); }
        if (localStorage.getItem('chatOpen')==='1') {
          chatWidget && chatWidget.classList.add('active');
          chatMessages && (chatMessages.scrollTop = chatMessages.scrollHeight);
        }
      });

      function checkNewMessages() {
        fetch('check_new_messages.php', { cache: 'no-store' })
          .then(res => res.json())
          .then(data => {
            if (!chatBubble) return;
            if (data.unread_count > 0) {
              let badge = chatBubble.querySelector('.unread-badge');
              if (!badge) {
                badge = document.createElement('span');
                badge.className = 'unread-badge';
                chatBubble.appendChild(badge);
              }
              badge.textContent = data.unread_count;
              if (!chatWidget.classList.contains('active')) {
                if (Notification && Notification.permission === 'granted') {
                  new Notification('Nuovi messaggi',{ body:`Hai ${data.unread_count} nuovo/i messaggio/i`, icon:'icon/notification.png' });
                } else if (Notification) {
                  Notification.requestPermission().then(p=>{ if(p==='granted') new Notification('Nuovi messaggi',{ body:`Hai ${data.unread_count} nuovo/i messaggio/i`, icon:'icon/notification.png'}); });
                }
              }
            } else {
              const badge = chatBubble.querySelector('.unread-badge');
              if (badge) badge.style.display = 'none';
            }
          });
      }

      function updateAdminChatBadges() {
        fetch('check_new_messages.php', { cache: 'no-store' })
          .then(r => r.json())
          .then(data => {
            const map = data.unread_by_user || {};
            document.querySelectorAll('.chat-badge[data-userid], .chat-badge.disabled[data-userid]').forEach(el => {
              const uid = el.getAttribute('data-userid');
              if (!uid) return;
              const count = parseInt(map[uid] || 0, 10);
              let badge = el.querySelector('.unread-badge');
              if (!badge) {
                badge = document.createElement('span');
                badge.className = 'unread-badge';
                badge.id = 'badge-' + uid;
                el.appendChild(badge);
              }
              if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'inline-block';
                el.classList.add('has-unread');
                const row = document.querySelector('tr[data-userid-row="' + uid + '"]');
                if (row) {
                  row.classList.add('new-unread-highlight');
                  setTimeout(() => row.classList.remove('new-unread-highlight'), 1800);
                }
              } else {
                badge.style.display = 'none';
                el.classList.remove('has-unread');
              }
            });
          })
          .catch(err => {
            console.error('admin badge update error', err);
          });
      }

      function attachAdminClickHandlers() {
        document.querySelectorAll('.chat-badge[data-userid]').forEach(el => {
          if (el.tagName.toLowerCase() !== 'a') return;
          if (el.dataset._handlerAttached === '1') return;
          el.dataset._handlerAttached = '1';
          el.addEventListener('click', function(e) {
            const userId = el.getAttribute('data-userid');
            if (!userId) return;
            e.preventDefault();
            fetch('mark_messages_read.php?user_id=' + encodeURIComponent(userId), { method: 'GET', cache: 'no-store' })
              .then(resp => resp.json())
              .then(() => {
                const badge = el.querySelector('.unread-badge');
                if (badge) badge.style.display = 'none';
                window.location.href = el.href;
              })
              .catch(() => {
                window.location.href = el.href;
              });
          });
        });
      }

      if (!isAdmin && 'Notification' in window) {
        Notification.requestPermission();
        checkNewMessages();
        setInterval(checkNewMessages, 30000);
      }
      
      if (isAdmin) {
        updateAdminChatBadges();
        attachAdminClickHandlers();
        setInterval(() => { updateAdminChatBadges(); attachAdminClickHandlers(); }, 10000);
      }

      <?php if ($force_password_change): ?>
        const pwModal = document.getElementById('modal-change-pw');
        if (pwModal) {
          pwModal.classList.add('open');
          const firstInp = pwModal.querySelector('input');
          firstInp && firstInp.focus();
        }
      <?php endif; ?>
      <?php if ($cp_success): ?>
        const modal = document.getElementById('modal-change-pw');
        modal && modal.classList.remove('open');
      <?php endif; ?>

      const tabButtons = document.querySelectorAll('.tab-btn');
      const tabContents = document.querySelectorAll('.tab-content');

      tabButtons.forEach(button => {
          button.addEventListener('click', () => {
              const tabId = button.getAttribute('data-tab');
              
              tabButtons.forEach(btn => btn.classList.remove('active'));
              tabContents.forEach(content => content.classList.remove('active'));
              
              button.classList.add('active');
              document.getElementById(`${tabId}-tab`).classList.add('active');
          });
      });
    });
  </script>

  <div id="footer">
    <p>&copy; <?= date('Y') ?> PALESTRW. Tutti i diritti riservati.</p>
  </div>
</body>
</html>