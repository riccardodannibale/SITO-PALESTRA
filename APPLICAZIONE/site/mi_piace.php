<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
error_reporting(E_ALL);

$backupBaseDir = __DIR__ . '/backup';

define('WEIGHT_LIKE', 0.5);
define('WEIGHT_UTILITA', 0.8);
define('WEIGHT_ACCORDO', 0.25);
define('MIN_SCORE', 0.0);
define('MAX_SCORE', 100.0);

function makeBackup(string $filePath, string $backupBaseDir) {
    if (!file_exists($filePath)) {
        return false;
    }
    if (!is_dir($backupBaseDir)) {
        if (!mkdir($backupBaseDir, 0777, true) && !is_dir($backupBaseDir)) {
            error_log("Impossibile creare la cartella di backup: $backupBaseDir");
            return false;
        }
    }
    $basename = basename($filePath);
    $backupName = $backupBaseDir . '/' . $basename . '.bak_' . date('Ymd_His');
    if (!@copy($filePath, $backupName)) {
        error_log("Backup fallito per $filePath in $backupName");
        return false;
    }
    return $backupName;
}

function normalize_username(?string $u): ?string {
    if ($u === null) return null;
    $u = trim($u);
    if ($u === '') return null;
    return mb_strtolower($u, 'UTF-8');
}
function safe_float($v, $default = 0.0) {
    if ($v === null) return $default;
    $s = trim((string)$v);
    if ($s === '') return $default;
    $s = str_replace(',', '.', $s);
    if (is_numeric($s)) return floatval($s);
    return $default;
}
function json_error($msg) {
    error_log("[mi_piace] $msg");
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

function update_reputation_for_user(string $usernameToUpdate) {
    $reputationFile = __DIR__ . '/xml/reputazione.xml';
    $forumFile = __DIR__ . '/xml/forum.xml';
    $backupDir = __DIR__ . '/backup';

    $targetNorm = normalize_username($usernameToUpdate);
    if ($targetNorm === null) return false;

    $likes = 0;
    $ratingsCount = 0;
    $sum_utilita = 0.0;
    $sum_accordo = 0.0;
    $latestActivity = null;

    if (file_exists($forumFile) && is_readable($forumFile)) {
        libxml_use_internal_errors(true);
        $fDoc = new DOMDocument();
        if ($fDoc->load($forumFile)) {
            foreach ($fDoc->getElementsByTagName('thread') as $t) {
                foreach ($t->getElementsByTagName('commento') as $c) {
                    $authorNode = $c->getElementsByTagName('autore')->item(0);
                    $authorRaw = $authorNode ? trim($authorNode->textContent) : null;
                    if (normalize_username($authorRaw) !== $targetNorm) continue;

                    $consensiNode = $c->getElementsByTagName('consensi')->item(0);
                    if ($consensiNode) {
                        $consensoElements = $consensiNode->getElementsByTagName('consenso');
                        if ($consensoElements->length > 0) {
                            $likes += $consensoElements->length;
                        } else {
                            $consensiText = trim($consensiNode->textContent);
                            if (is_numeric($consensiText)) $likes += (int)$consensiText;
                        }
                    }

                    $ratingsNode = $c->getElementsByTagName('valutazioni_commento')->item(0);
                    if ($ratingsNode) {
                        foreach ($ratingsNode->getElementsByTagName('valutazione') as $rating) {
                            $utilitaNode = $rating->getElementsByTagName('utilita')->item(0);
                            $accordoNode = $rating->getElementsByTagName('accordo')->item(0);
                            $utilita = $utilitaNode ? safe_float($utilitaNode->textContent) : 0.0;
                            $accordo = $accordoNode ? safe_float($accordoNode->textContent) : 0.0;
                            $ratingsCount += 1;
                            $sum_utilita += $utilita;
                            $sum_accordo += $accordo;
                        }
                    }

                    $dateNode = $c->getElementsByTagName('data')->item(0);
                    if ($dateNode) {
                        $dateStr = trim($dateNode->textContent);
                        if ($dateStr !== '') {
                            try {
                                $dt = new DateTimeImmutable($dateStr);
                            } catch (Exception $e) {
                                $ts = strtotime($dateStr);
                                if ($ts !== false) $dt = (new DateTimeImmutable())->setTimestamp($ts);
                                else $dt = null;
                            }
                            if (!empty($dt) && ($latestActivity === null || $dt > $latestActivity)) $latestActivity = $dt;
                        }
                    }
                }
            }
        }
        libxml_clear_errors();
    }

    $likesContribution = floatval($likes) * WEIGHT_LIKE;
    $ratingScale = ($ratingsCount > 0) ? log10(1 + $ratingsCount) : 0.0;
    $avgUtilita = ($ratingsCount > 0) ? ($sum_utilita / $ratingsCount) : 0.0;
    $avgAccordo = ($ratingsCount > 0) ? ($sum_accordo / $ratingsCount) : 0.0;
    $utilitaContribution = ($avgUtilita > 0) ? ($avgUtilita * WEIGHT_UTILITA * $ratingScale) : 0.0;
    $accordoContribution = ($avgAccordo > 0) ? ($avgAccordo * WEIGHT_ACCORDO * $ratingScale) : 0.0;

    libxml_use_internal_errors(true);
    $repDoc = new DOMDocument('1.0', 'UTF-8');
    $repDoc->preserveWhiteSpace = false;
    $repDoc->formatOutput = true;

    if (!file_exists($reputationFile) || filesize($reputationFile) === 0) {
        if (!is_dir(dirname($reputationFile))) mkdir(dirname($reputationFile), 0755, true);
        $repDoc->appendChild($repDoc->createElement('reputazioni'));
        file_put_contents($reputationFile, $repDoc->saveXML(), LOCK_EX);
    }

    if (!$repDoc->load($reputationFile)) {
        $repDoc = new DOMDocument('1.0', 'UTF-8');
        $repDoc->preserveWhiteSpace = false;
        $repDoc->formatOutput = true;
        $repDoc->appendChild($repDoc->createElement('reputazioni'));
    }
    libxml_clear_errors();

    $found = null;
    $utenti = $repDoc->getElementsByTagName('utente');
    $maxId = 0;
    foreach ($utenti as $u) {
        $idAttr = $u->hasAttribute('id') ? intval($u->getAttribute('id')) : 0;
        if ($idAttr > $maxId) $maxId = $idAttr;
        $usernameNode = $u->getElementsByTagName('username')->item(0);
        $uname = $usernameNode ? trim($usernameNode->textContent) : '';
        if (normalize_username($uname) === $targetNorm) {
            $found = $u;
            break;
        }
    }

    if (!$found) {
        $maxId++;
        $found = $repDoc->createElement('utente');
        $found->setAttribute('id', (string)$maxId);
        $unameNode = $repDoc->createElement('username', $targetNorm);
        $found->appendChild($unameNode);
        $pNode = $repDoc->createElement('punteggio', '0.00');
        $pNode->setAttribute('base', '0.00');
        $found->appendChild($pNode);
        $ultimaNode = $repDoc->createElement('ultima_attivita', ($latestActivity ? $latestActivity->format('Y-m-d') : ''));
        $found->appendChild($ultimaNode);
        $repDoc->documentElement->appendChild($found);
    }

    $punteggioNode = $found->getElementsByTagName('punteggio')->item(0);
    if (!$punteggioNode) {
        $punteggioNode = $repDoc->createElement('punteggio', '0.00');
        $found->appendChild($punteggioNode);
        $punteggioNode->setAttribute('base', '0.00');
        $baseScore = 0.0;
    } else {
        if ($punteggioNode->hasAttribute('base')) {
            $baseScore = safe_float($punteggioNode->getAttribute('base'), 0.0);
        } else {
            $textVal = trim($punteggioNode->textContent);
            $baseScore = safe_float($textVal, 0.0);
            $punteggioNode->setAttribute('base', number_format($baseScore,2,'.',''));
        }
    }

    $adjustedBase = $baseScore + $likesContribution + $utilitaContribution + $accordoContribution;
    $currentScore = $adjustedBase;

    $ultimaAttNode = $found->getElementsByTagName('ultima_attivita')->item(0);
    if ($latestActivity) {
        $newDateStr = $latestActivity->format('Y-m-d');
        if ($ultimaAttNode) {
            $existing = trim($ultimaAttNode->textContent);
            if ($existing === '' || new DateTimeImmutable($existing) < $latestActivity) {
                $ultimaAttNode->nodeValue = $newDateStr;
            }
        } else {
            $ultimaAttNode = $repDoc->createElement('ultima_attivita', $newDateStr);
            $found->appendChild($ultimaAttNode);
        }
    }

    if ($ultimaAttNode) {
        $ultima_attivita_str = trim($ultimaAttNode->textContent);
        if ($ultima_attivita_str !== '') {
            try {
                $ultima_attivita = new DateTimeImmutable($ultima_attivita_str);
                $now = new DateTimeImmutable();
                if ($ultima_attivita <= $now) {
                    $diff = $now->diff($ultima_attivita);
                    $daysInactive = $diff->days;
                    if ($daysInactive > 30) {
                        $decayDays = $daysInactive - 30;
                        $decayFactor = pow(0.99, $decayDays);
                        $currentScore = $adjustedBase * $decayFactor;
                    }
                }
            } catch (Exception $e) {
                $currentScore = $adjustedBase;
            }
        }
    }

    if (!is_finite($currentScore)) $currentScore = 0.0;
    $currentScore = max(MIN_SCORE, min(MAX_SCORE, round($currentScore, 2)));

    $punteggioNode->nodeValue = number_format($currentScore,2,'.','');
    $punteggioNode->setAttribute('last_calc', (new DateTimeImmutable())->format(DATE_ATOM));
    $punteggioNode->setAttribute('last_value', number_format($currentScore,2,'.',''));
    $punteggioNode->setAttribute('base', number_format($baseScore,2,'.',''));

    if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);
    $repBackup = $backupDir . '/' . basename($reputationFile) . '.bak_' . date('Ymd_His');
    if (file_exists($reputationFile)) @copy($reputationFile, $repBackup);

    $tmp = $reputationFile . '.tmp_' . uniqid();
    $xmlString = $repDoc->saveXML();
    if (file_put_contents($tmp, $xmlString, LOCK_EX) !== false) {
        @rename($tmp, $reputationFile);
    } else {
        @unlink($tmp);
    }

    return true;
}


$username = $_SESSION['username'] ?? null;
if (!$username) json_error('Utente non autenticato');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) json_error('Payload JSON non valido');
$thread_id  = isset($input['thread_id']) ? (string)$input['thread_id'] : null;
$comment_id = isset($input['comment_id']) ? (string)$input['comment_id'] : null;
if (!$thread_id || !$comment_id) json_error('Parametri mancanti');

$xmlFile = __DIR__ . '/xml/forum.xml';
if (!file_exists($xmlFile) || !is_readable($xmlFile) || !is_writable($xmlFile)) json_error('forum.xml non accessibile');

libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->preserveWhiteSpace = false;
$doc->formatOutput = true;
if (!$doc->load($xmlFile)) {
    $errs = libxml_get_errors();
    $msg = 'forum.xml malformato. ';
    foreach ($errs as $e) $msg .= trim($e->message) . '; ';
    libxml_clear_errors();

    $backupPath = makeBackup($xmlFile, $backupBaseDir);
    if ($backupPath !== false) {
        $msg .= " Backup creato: " . basename($backupPath);
    } else {
        $msg .= " Impossibile creare backup automatico.";
    }

    json_error($msg);
}
libxml_clear_errors();

$threadNode = null;
foreach ($doc->getElementsByTagName('thread') as $t) {
    if ($t->hasAttribute('id') && $t->getAttribute('id') === $thread_id) {
        $threadNode = $t;
        break;
    }
}
if (!$threadNode) json_error('Thread non trovato');

if ($threadNode->hasAttribute('locked') && strtolower($threadNode->getAttribute('locked')) === 'true') {
    json_error('Impossibile mettere mi piace: thread bloccato');
}

$commentNode = null;
foreach ($threadNode->getElementsByTagName('commento') as $c) {
    if ($c->hasAttribute('id') && $c->getAttribute('id') === $comment_id) {
        $commentNode = $c;
        break;
    }
}
if (!$commentNode) json_error('Commento non trovato');

$authorNode = $commentNode->getElementsByTagName('autore')->item(0);
$commentAuthor = $authorNode ? trim($authorNode->textContent) : null;
if ($commentAuthor && normalize_username($commentAuthor) === normalize_username($username)) {
    json_error('Non puoi mettere mi piace al tuo commento');
}

$consensiNode = null;
foreach ($commentNode->childNodes as $child) {
    if ($child->nodeType === XML_ELEMENT_NODE && $child->nodeName === 'consensi') {
        $consensiNode = $child;
        break;
    }
}
if (!$consensiNode) {
    $consensiNode = $doc->createElement('consensi');
    $commentNode->appendChild($consensiNode);
}

foreach ($consensiNode->getElementsByTagName('consenso') as $cn) {
    if (normalize_username($cn->textContent) === normalize_username($username)) {
        json_error('Hai già messo il pollice!');
    }
}

$newCons = $doc->createElement('consenso');
$newCons->appendChild($doc->createTextNode($username));
$consensiNode->appendChild($newCons);

if (file_exists($xmlFile)) {
    makeBackup($xmlFile, $backupBaseDir);
}

$tmp = $xmlFile . '.tmp_' . uniqid();
$xmlString = $doc->saveXML();

if (file_put_contents($tmp, $xmlString, LOCK_EX) === false) {
    json_error('Errore salvataggio temporaneo');
}
if (!@rename($tmp, $xmlFile)) {
    if (file_put_contents($xmlFile, $xmlString, LOCK_EX) === false) {
        @unlink($tmp);
        json_error('Errore salvataggio definitivo');
    }
    @unlink($tmp);
}

if ($commentAuthor) {
    update_reputation_for_user($commentAuthor);
}

$likedUsers = [];
foreach ($consensiNode->getElementsByTagName('consenso') as $cn) {
    $likedUsers[] = trim($cn->textContent);
}

echo json_encode(['success' => true, 'liked_users' => $likedUsers]);
exit;
