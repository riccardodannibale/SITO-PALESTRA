<?php
session_start();

include __DIR__ . '/db.php';

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

if (!isset($_SESSION['id_user'])) {
    $_SESSION['add_error'] = 'Devi effettuare il login.';
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? trim($_GET['id']) : '';
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if ($id === '' || !in_array($action, ['confirm', 'reject'], true)) {
    $_SESSION['add_error'] = 'Parametri mancanti o non validi.';
    header('Location: profilo.php');
    exit;
}

$is_admin = false;
$uid = (int)$_SESSION['id_user'];
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id_user = ?");
if ($stmt) {
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->bind_result($is_admin_flag);
    if ($stmt->fetch()) {
        $is_admin = ($is_admin_flag == 1);
    }
    $stmt->close();
}

if (!$is_admin) {
    $_SESSION['add_error'] = 'Azione non autorizzata.';
    header('Location: profilo.php');
    exit;
}

$userAbbPath = __DIR__ . '/xml/user_abbonamenti.xml';
$xml = getXML($userAbbPath, 'user_abbonamenti');
if (!$xml) {
    $_SESSION['add_error'] = 'File delle sottoscrizioni non trovato o non leggibile.';
    header('Location: profilo.php');
    exit;
}
 
$found = false;
foreach ($xml->abbonamento_utente as $node) {
    $attrId = isset($node['id']) ? (string)$node['id'] : '';
    if ($attrId === $id) {
        $found = true;
    } else {
        $found = false;
    }

    if ($found) {
        $currStato = isset($node->stato) ? (string)$node->stato : '';

        if ($action === 'confirm') {
            if (in_array($currStato, ['attivo'], true)) {
                $_SESSION['add_success'] = 'L\'abbonamento è già attivo.';
                header('Location: profilo.php');
                exit;
            }

            $node->stato = 'attivo';
            $node->addChild('approvato_da', (string)$_SESSION['id_user']);
            $node->addChild('data_approvazione', date('Y-m-d'));

            if (!isset($node->data_inizio) || trim((string)$node->data_inizio) === '') {
                $node->data_inizio = date('Y-m-d');
            }

            if ((!isset($node->data_scadenza) || trim((string)$node->data_scadenza) === '') && isset($node->id_abbonamento)) {
                $idAbbon = (int)$node->id_abbonamento;
                $abbXML = getXML(__DIR__ . '/xml/abbonamenti.xml', 'abbonamenti');
                if ($abbXML) {
                    foreach ($abbXML->abbonamento as $abb) {
                        $aattr = $abb->attributes();
                        if ((int)$aattr['id'] === $idAbbon) {
                            $durata = (int)$abb->durata_mesi;
                            if ($durata > 0) {
                                $node->data_scadenza = date('Y-m-d', strtotime($node->data_inizio . " +{$durata} month"));
                            }
                            break;
                        }
                    }
                }
            }

            if (saveXML($xml, $userAbbPath) === false) {
                $_SESSION['add_error'] = 'Errore salvataggio XML durante la conferma.';
            } else {
                $_SESSION['add_success'] = 'Richiesta confermata: abbonamento attivato.';
            }

        } elseif ($action === 'reject') {
            if (in_array($currStato, ['rifiutato'], true)) {
                $_SESSION['add_error'] = 'La richiesta è già stata rifiutata.';
                header('Location: profilo.php');
                exit;
            }

            $node->stato = 'rifiutato';
            $node->addChild('rifiutato_da', (string)$_SESSION['id_user']);
            $node->addChild('data_rifiuto', date('Y-m-d'));

            if (saveXML($xml, $userAbbPath) === false) {
                $_SESSION['add_error'] = 'Errore salvataggio XML durante il rifiuto.';
            } else {
                $_SESSION['add_success'] = 'Richiesta rifiutata con successo.';
            }
        }

        header('Location: profilo.php');
        exit;
    }
}

$_SESSION['add_error'] = 'Richiesta non trovata. ID non valido.';
header('Location: profilo.php');
exit;
