<?php
session_start();

function normalize_username(?string $u): ?string {
    if ($u === null) return null;
    $u = trim($u);
    if ($u === '') return null;
    return mb_strtolower($u, 'UTF-8');
}

function safe_float_from_node($node, $default = 0.0) {
    if (!$node) return $default;
    $txt = trim($node->textContent ?? '');
    if ($txt === '') return $default;
    $txt2 = str_replace(',', '.', $txt);
    if (is_numeric($txt2)) return floatval($txt2);
    return $default;
}

function safe_float_from_str($s, $default = 0.0) {
    if ($s === null) return $default;
    $s = trim($s);
    if ($s === '') return $default;
    $s2 = str_replace(',', '.', $s);
    if (is_numeric($s2)) return floatval($s2);
    return $default;
}

define('WEIGHT_LIKE', 0.5);
define('WEIGHT_UTILITA', 0.8);
define('WEIGHT_ACCORDO', 0.25);
define('MIN_SCORE', 0.0);
define('MAX_SCORE', 100.0);
define('MIN_REPUTATION_ON_EMPTY', 0.0);

$reputationFile = __DIR__ . '/xml/reputazione.xml';
$forumFile      = __DIR__ . '/xml/forum.xml';
$xmlMsg         = __DIR__ . '/xml/comunicazioni.xml';
$xmlAb          = __DIR__ . '/xml/user_abbonamenti.xml';

$id_user  = $_SESSION['id_user'] ?? null;
$is_admin = false;
$username = null;

if ($id_user) {
    if (file_exists(__DIR__ . '/db.php')) {
        include __DIR__ . '/db.php';
        if (isset($conn) && $conn) {
            $stmt = $conn->prepare("SELECT is_admin, username FROM users WHERE id_user = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id_user);
                $stmt->execute();
                $stmt->bind_result($flag_admin, $db_username);
                $stmt->fetch();
                $stmt->close();
                $is_admin = ($flag_admin == 1);
                $username = $db_username;
            }
        }
    }
}
$usernameNormalized = normalize_username($username);

$is_subscribed = false;
$is_expiring  = false;
if ($id_user && file_exists($xmlAb)) {
    libxml_use_internal_errors(true);
    $abDoc = new DOMDocument();
    if ($abDoc->load($xmlAb)) {
        $today = new DateTimeImmutable();
        foreach ($abDoc->getElementsByTagName('abbonamento_utente') as $ab) {
            $uidNode = $ab->getElementsByTagName('id_user')->item(0);
            $statoNode = $ab->getElementsByTagName('stato')->item(0);
            $inizioNode = $ab->getElementsByTagName('data_inizio')->item(0);
            $scadeNode = $ab->getElementsByTagName('data_scadenza')->item(0);

            $uid     = $uidNode ? trim($uidNode->textContent) : null;
            $stato   = $statoNode ? trim($statoNode->textContent) : null;

            $inizio  = null;
            $scade   = null;
            try {
                if ($inizioNode && trim($inizioNode->textContent) !== '') {
                    $inizio = new DateTimeImmutable(trim($inizioNode->textContent));
                }
                if ($scadeNode && trim($scadeNode->textContent) !== '') {
                    $scade = new DateTimeImmutable(trim($scadeNode->textContent));
                }
            } catch (Exception $e) {
                $inizio = null;
                $scade = null;
            }

            if ($uid == $id_user && $stato === 'attivo' && $inizio && $scade) {
                $scade_end = $scade->modify('+1 day')->setTime(0,0);
                if ($today >= $inizio && $today < $scade_end) {
                    $is_subscribed = true;
                    $daysLeft = (int)$today->diff($scade)->days;
                    if ($daysLeft <= 7) $is_expiring = true;
                    break;
                }
            }
        }
    }
    libxml_clear_errors();
}

$showConfirm = isset($_GET['ok']) && $_GET['ok'] === '1';
$expandThreadId = $_GET['thread_id'] ?? null;

$userStats = [];
if (file_exists($forumFile)) {
    libxml_use_internal_errors(true);
    $fDoc = new DOMDocument();
    if ($fDoc->load($forumFile)) {
        foreach ($fDoc->getElementsByTagName('thread') as $t) {
            foreach ($t->getElementsByTagName('commento') as $c) {
                $authorNode = $c->getElementsByTagName('autore')->item(0);
                $authorRaw = $authorNode ? trim($authorNode->textContent) : null;
                $key = normalize_username($authorRaw);
                if ($key === null) continue;

                if (!isset($userStats[$key])) {
                    $userStats[$key] = ['likes'=>0, 'ratings_count'=>0, 'sum_utilita'=>0.0, 'sum_accordo'=>0.0];
                }
                $consensiNode = $c->getElementsByTagName('consensi')->item(0);
                if ($consensiNode) {
                    $consensoElements = $consensiNode->getElementsByTagName('consenso');
                    if ($consensoElements->length > 0) {
                        $userStats[$key]['likes'] += $consensoElements->length;
                    } else {
                        $consensiText = trim($consensiNode->textContent);
                        if (is_numeric($consensiText)) {
                            $userStats[$key]['likes'] += (int)$consensiText;
                        }
                    }
                }

                $ratingsNode = $c->getElementsByTagName('valutazioni_commento')->item(0);
                if ($ratingsNode) {
                    foreach ($ratingsNode->getElementsByTagName('valutazione') as $rating) {
                        $utilitaNode = $rating->getElementsByTagName('utilita')->item(0);
                        $accordoNode = $rating->getElementsByTagName('accordo')->item(0);
                        $utilita = safe_float_from_node($utilitaNode, 0.0);
                        $accordo = safe_float_from_node($accordoNode, 0.0);

                        $userStats[$key]['ratings_count'] += 1;
                        $userStats[$key]['sum_utilita'] += $utilita;
                        $userStats[$key]['sum_accordo'] += $accordo;
                    }
                }
            }
        }
    }
    libxml_clear_errors();
}

$reputationScoresByLower = [];
$reputationScoresDisplay = [];

libxml_use_internal_errors(true);
$repDoc = new DOMDocument('1.0', 'UTF-8');
$repDoc->preserveWhiteSpace = false;
$repDoc->formatOutput = true;

if (!file_exists($reputationFile) || filesize($reputationFile) === 0) {
    if (!is_dir(dirname($reputationFile))) mkdir(dirname($reputationFile), 0755, true);
    $repDoc->appendChild($repDoc->createElement('reputazioni'));
    file_put_contents($reputationFile, $repDoc->saveXML(), LOCK_EX);
}

$loaded = $repDoc->load($reputationFile);
if (!$loaded) {
    $repDoc = new DOMDocument('1.0', 'UTF-8');
    $repDoc->preserveWhiteSpace = false;
    $repDoc->formatOutput = true;
    $repDoc->appendChild($repDoc->createElement('reputazioni'));
    file_put_contents($reputationFile, $repDoc->saveXML(), LOCK_EX);
    $repDoc->load($reputationFile);
}
libxml_clear_errors();

$existingUsers = [];
$utenti = $repDoc->getElementsByTagName('utente');
$maxId = 0;
foreach ($utenti as $u) {
    $idAttr = $u->hasAttribute('id') ? intval($u->getAttribute('id')) : 0;
    if ($idAttr > $maxId) $maxId = $idAttr;
    $usernameNode = $u->getElementsByTagName('username')->item(0);
    $uname = $usernameNode ? trim($usernameNode->textContent) : '';
    $norm = normalize_username($uname);
    if ($norm !== null) $existingUsers[$norm] = $u;
}


foreach ($userStats as $statKey => $_) {
    if ($statKey === '') continue;
    if (!isset($existingUsers[$statKey])) {
        $maxId++;
        $utente = $repDoc->createElement('utente');
        $utente->setAttribute('id', (string)$maxId);

        $unameNode = $repDoc->createElement('username', $statKey);
        $utente->appendChild($unameNode);

        $pNode = $repDoc->createElement('punteggio', '0.00');
        $pNode->setAttribute('base', number_format(0.0, 2, '.', ''));
        $utente->appendChild($pNode);

        $ultimaNode = $repDoc->createElement('ultima_attivita', '');
        $utente->appendChild($ultimaNode);

        $repDoc->documentElement->appendChild($utente);
        $existingUsers[$statKey] = $utente;
    }
}

$todayRep = new DateTimeImmutable();
$utenti = $repDoc->getElementsByTagName('utente');

foreach ($utenti as $utente) {
    $usernameNode = $utente->getElementsByTagName('username')->item(0);
    $punteggioNode = $utente->getElementsByTagName('punteggio')->item(0);
    $ultimaAttNode = $utente->getElementsByTagName('ultima_attivita')->item(0);

    $currentUsername = $usernameNode ? trim($usernameNode->textContent) : null;
    if (!$currentUsername) continue;

    $key = normalize_username($currentUsername) ?? $currentUsername;

    $likes = $userStats[$key]['likes'] ?? 0;
    $ratingsCount = $userStats[$key]['ratings_count'] ?? 0;
    $avgUtilita = ($ratingsCount > 0) ? ($userStats[$key]['sum_utilita'] / $ratingsCount) : 0.0;
    $avgAccordo = ($ratingsCount > 0) ? ($userStats[$key]['sum_accordo'] / $ratingsCount) : 0.0;

    $likesContribution = floatval($likes) * WEIGHT_LIKE;
    $ratingScale = ($ratingsCount > 0) ? log10(1 + $ratingsCount) : 0.0;
    $utilitaContribution = ($avgUtilita > 0) ? ($avgUtilita * WEIGHT_UTILITA * $ratingScale) : 0.0;
    $accordoContribution = ($avgAccordo > 0) ? ($avgAccordo * WEIGHT_ACCORDO * $ratingScale) : 0.0;

    $baseScore = 0.0;
    if ($punteggioNode) {
        if ($punteggioNode->hasAttribute('base')) {
            $baseScore = safe_float_from_str($punteggioNode->getAttribute('base'), 0.0);
        } else {
            $textVal = trim($punteggioNode->textContent ?? '');
            if ($textVal !== '') {
                $baseScore = safe_float_from_str($textVal, 0.0);
            } else {
                $baseScore = 0.0;
            }
        }
    }

    $adjustedBase = $baseScore + $likesContribution + $utilitaContribution + $accordoContribution;
    $currentScore = $adjustedBase;


    if (($likes === 0) && ($ratingsCount === 0) && floatval($adjustedBase) <= 0.0) {
        $currentScore = floatval(MIN_REPUTATION_ON_EMPTY);
    } else {
        if ($ultimaAttNode) {
            $ultima_attivita_str = trim($ultimaAttNode->textContent);
            if ($ultima_attivita_str !== '') {
                try {
                    $ultima_attivita = new DateTimeImmutable($ultima_attivita_str);
                    if ($ultima_attivita > $todayRep) {
                        $currentScore = $adjustedBase;
                    } else {
                        $diff = $todayRep->diff($ultima_attivita);
                        $daysInactive = $diff->days;
                        if ($daysInactive > 30) {
                            $decayDays = $daysInactive - 30;
                            $decayFactor = pow(0.99, $decayDays);
                            $currentScore = $adjustedBase * $decayFactor;
                        } else {
                            $currentScore = $adjustedBase;
                        }
                    }
                } catch (Exception $e) {

                    $currentScore = $adjustedBase;
                }
            } else {
                $currentScore = $adjustedBase;
            }
        } else {
            $currentScore = $adjustedBase;
        }
    }

    if (!is_finite($currentScore)) $currentScore = 0.0;
    $currentScore = max(MIN_SCORE, min(MAX_SCORE, round($currentScore, 2)));

 
    try {
        if (!$punteggioNode) {
            $punteggioNode = $repDoc->createElement('punteggio', number_format($currentScore, 2, '.', ''));
            $punteggioNode->setAttribute('base', number_format($baseScore, 2, '.', ''));
            $utente->appendChild($punteggioNode);
        } else {
  
            $punteggioNode->nodeValue = number_format($currentScore, 2, '.', '');
            $punteggioNode->setAttribute('base', number_format($baseScore, 2, '.', ''));
        }
        $punteggioNode->setAttribute('last_calc', (new DateTimeImmutable())->format(DATE_ATOM));
        $punteggioNode->setAttribute('last_value', number_format($currentScore, 2, '.', ''));
    } catch (Exception $e) {
  
    }

    $reputationScoresByLower[$key] = $currentScore;

    $reputationScoresDisplay[$currentUsername] = $currentScore;
}


try {
    $backupDir = __DIR__ . '/backup';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
    }
    $filename   = basename($reputationFile);
    $backupPath = $backupDir . '/' . $filename . '.bak_' . date('Ymd_His');
    if (!file_exists($backupPath) && file_exists($reputationFile)) {
        copy($reputationFile, $backupPath);
    }
    $tmpFile = $reputationFile . '.tmp_' . uniqid();
    file_put_contents($tmpFile, $repDoc->saveXML(), LOCK_EX);
    rename($tmpFile, $reputationFile);
} catch (Exception $e) {

}

function getReputationClass($score) {
    if ($score >= 70) return 'high-rep';
    if ($score >= 30) return 'medium-rep';
    return 'low-rep';
}

function getReputationForAuthor(string $author, array $mapLower, array $mapDisplay) {
    $norm = normalize_username($author);
    if ($norm !== null && isset($mapLower[$norm])) return floatval($mapLower[$norm]);
    if (isset($mapDisplay[$author])) return floatval($mapDisplay[$author]);
    foreach ($mapDisplay as $k => $v) {
        if (strcasecmp($k, $author) === 0) return floatval($v);
    }
    return 0.0;
}

$implementation = new DOMImplementation();
$doc = new DOMDocument('1.0','UTF-8');
$doc->preserveWhiteSpace = false;
$doc->formatOutput    = true;
libxml_use_internal_errors(true);

if (file_exists($xmlMsg)) {
    $loaded = $doc->load($xmlMsg);
    if (!$loaded) {
        copy($xmlMsg, $xmlMsg . '.bak_' . date('Ymd_His'));
        $dtd = $implementation->createDocumentType('comunicazioni','','../dtd/comunicazioni.dtd');
        $doc = $implementation->createDocument(null,'',$dtd);
        $doc->encoding = 'UTF-8';
        $root = $doc->createElement('comunicazioni');
        $doc->appendChild($root);
        file_put_contents($xmlMsg, $doc->saveXML(), LOCK_EX);
    }
} else {
    $dtd = $implementation->createDocumentType('comunicazioni','','../dtd/comunicazioni.dtd');
    $doc = $implementation->createDocument(null,'',$dtd);
    $doc->encoding = 'UTF-8';
    $root = $doc->createElement('comunicazioni');
    $doc->appendChild($root);
    if(!is_dir(dirname($xmlMsg))) mkdir(dirname($xmlMsg),0755,true);
    file_put_contents($xmlMsg, $doc->saveXML(), LOCK_EX);
}

$messaggi = [];

foreach($doc->getElementsByTagName('messaggio') as $m) {
    $titoloNode = $m->getElementsByTagName('titolo')->item(0);
    $contenutoNode = $m->getElementsByTagName('contenuto')->item(0);
    $titolo = $titoloNode ? trim($titoloNode->textContent) : '';
    $contenuto = $contenutoNode ? trim($contenutoNode->textContent) : '';

    $autoreAttr = $m->hasAttribute('autore') ? trim($m->getAttribute('autore')) : null;

    $data_invio = '';
    if ($m->hasAttribute('data_invio')) {
        $data_invio = trim($m->getAttribute('data_invio'));
    } else {
        $dataNode = $m->getElementsByTagName('data_invio')->item(0);
        if ($dataNode) $data_invio = trim($dataNode->textContent);
    }

    $messaggi[] = [
        'titolo'     => $titolo,
        'contenuto'  => $contenuto,
        'data_invio' => $data_invio ?: '',
        'autore'     => $autoreAttr
    ];
}

usort($messaggi, function($a, $b) {
    $ta = strtotime($a['data_invio']);
    $tb = strtotime($b['data_invio']);
    if ($ta !== false && $tb !== false) return $tb <=> $ta;
    return strcmp($b['data_invio'], $a['data_invio']);
});

libxml_clear_errors();

$forumThreads  = [];
$userContributions = [];

if ($is_admin || $is_subscribed) {
    $forumDoc = new DOMDocument();
    if (file_exists($forumFile) && $forumDoc->load($forumFile)) {
        foreach ($forumDoc->getElementsByTagName('thread') as $t) {
            $threadId = $t->hasAttribute('id') ? $t->getAttribute('id') : md5($t->getElementsByTagName('titolo')->item(0)->textContent);
            $threadAuthorRaw = $t->getElementsByTagName('autore')->item(0)->textContent ?? '';
            $threadAuthorNormalized = normalize_username($threadAuthorRaw) ?? '';
            $threadScore = getReputationForAuthor($threadAuthorRaw, $reputationScoresByLower, $reputationScoresDisplay);

            $pinned = $t->hasAttribute('pinned') ? ($t->getAttribute('pinned') === 'true' || $t->getAttribute('pinned') === '1') : false;
            $locked = $t->hasAttribute('locked') ? ($t->getAttribute('locked') === 'true' || $t->getAttribute('locked') === '1') : false;
            $last_edit_by = $t->hasAttribute('last_edit_by') ? $t->getAttribute('last_edit_by') : null;
            $last_edit_date = $t->hasAttribute('last_edit_date') ? $t->getAttribute('last_edit_date') : null;

            $thread = [
                'id'             => $threadId,
                'titolo'         => $t->getElementsByTagName('titolo')->item(0)->textContent ?? '',
                'autore'         => $threadAuthorRaw,
                'data_creazione' => $t->getElementsByTagName('data_creazione')->item(0)->textContent ?? '',
                'contenuto'      => $t->getElementsByTagName('contenuto')->item(0)->textContent ?? '',
                'reputazione'    => $threadScore,
                'pinned'         => $pinned,
                'locked'         => $locked,
                'last_edit_by'   => $last_edit_by,
                'last_edit_date' => $last_edit_date,
                'commenti'       => []
            ];

            if ($username && strcasecmp($threadAuthorRaw, $username) === 0) {
                $userContributions[] = [
                    'type' => 'thread_created',
                    'date' => $thread['data_creazione'],
                    'title' => $thread['titolo'],
                    'content' => $thread['contenuto']
                ];
            }

            $comments = [];
            foreach ($t->getElementsByTagName('commento') as $c) {
                $commentAuthor = $c->getElementsByTagName('autore')->item(0)->textContent ?? '';
                $commentDate = $c->getElementsByTagName('data')->item(0)->textContent ?? '';
                $commentId = $c->hasAttribute('id') ? $c->getAttribute('id') : md5($commentAuthor . $commentDate);
                $commentScore = getReputationForAuthor($commentAuthor, $reputationScoresByLower, $reputationScoresDisplay);
                $last_edit_by = $c->hasAttribute('last_edit_by') ? $c->getAttribute('last_edit_by') : null;
                $last_edit_date = $c->hasAttribute('last_edit_date') ? $c->getAttribute('last_edit_date') : null;

                $commentRatings = [];
                $ratingsNode = $c->getElementsByTagName('valutazioni_commento')->item(0);
                if ($ratingsNode) {
                    foreach ($ratingsNode->getElementsByTagName('valutazione') as $rating) {
                        $rater   = $rating->getAttribute('valutatore') ?? '';
                        $utilita = $rating->getElementsByTagName('utilita')->item(0)->textContent ?? '';
                        $accordo = $rating->getElementsByTagName('accordo')->item(0)->textContent ?? '';
                        $commentRatings[] = [
                            'valutatore' => $rater,
                            'utilita'    => $utilita,
                            'accordo'    => $accordo
                        ];

                        if ($username && strcasecmp($rater, $username) === 0) {
                            $userContributions[] = [
                                'type' => 'comment_rated',
                                'date' => $commentDate,
                                'comment_author' => $commentAuthor,
                                'comment_content' => $c->getElementsByTagName('contenuto')->item(0)->textContent ?? '',
                                'utilita' => $utilita,
                                'accordo' => $accordo
                            ];
                        }
                    }
                }

                $consensi = [];
                $consensiNode = $c->getElementsByTagName('consensi')->item(0);
                if ($consensiNode) {
                    $consensoElements = $consensiNode->getElementsByTagName('consenso');
                    if ($consensoElements->length > 0) {
                        foreach ($consensoElements as $cons) {
                            $consText = trim($cons->textContent ?? '');
                            if ($consText !== '') $consensi[] = $consText;
                            if ($username && strcasecmp($consText, $username) === 0) {
                                $userContributions[] = [
                                    'type' => 'comment_liked',
                                    'date' => $commentDate,
                                    'comment_author' => $commentAuthor,
                                    'comment_content' => $c->getElementsByTagName('contenuto')->item(0)->textContent ?? ''
                                ];
                            }
                        }
                    } else {
                        $consensiText = trim($consensiNode->textContent ?? '');
                        if (is_numeric($consensiText)) {
                            $consensi = array_fill(0, (int)$consensiText, 'unknown');
                        }
                    }
                }

                $comments[] = [
                    'id'          => $commentId,
                    'autore'      => $commentAuthor,
                    'testo'       => $c->getElementsByTagName('contenuto')->item(0)->textContent ?? '',
                    'data'        => $commentDate,
                    'consensi'    => $consensi,
                    'valutazioni' => $commentRatings,
                    'reputazione' => $commentScore,
                    'last_edit_by' => $last_edit_by,
                    'last_edit_date' => $last_edit_date,
                    'thread_locked' => $locked
                ];

                if ($username && strcasecmp($commentAuthor, $username) === 0) {
                    $userContributions[] = [
                        'type' => 'comment_created',
                        'date' => $commentDate,
                        'thread_title' => $thread['titolo'],
                        'content' => $c->getElementsByTagName('contenuto')->item(0)->textContent ?? ''
                    ];
                }
            }

            usort($comments, function($a, $b) {
                return $b['reputazione'] <=> $a['reputazione'];
            });

            $thread['commenti'] = $comments;
            $forumThreads[] = $thread;
        }

        function get_thread_last_activity_ts(array $thread): int {
            $cand = [];

            if (!empty($thread['data_creazione'])) {
                $ts = strtotime($thread['data_creazione']);
                if ($ts !== false) $cand[] = $ts;
            }

            if (!empty($thread['last_edit_date'])) {
                $ts = strtotime($thread['last_edit_date']);
                if ($ts !== false) $cand[] = $ts;
            }

            if (!empty($thread['commenti']) && is_array($thread['commenti'])) {
                foreach ($thread['commenti'] as $cm) {
                    if (!empty($cm['data'])) {
                        $ts = strtotime($cm['data']);
                        if ($ts !== false) $cand[] = $ts;
                    }
                    if (!empty($cm['last_edit_date'])) {
                        $ts = strtotime($cm['last_edit_date']);
                        if ($ts !== false) $cand[] = $ts;
                    }
                }
            }

            return empty($cand) ? 0 : max($cand);
        }

        usort($forumThreads, function($a, $b) {
            if (!empty($a['pinned']) && empty($b['pinned'])) return -1;
            if (empty($a['pinned']) && !empty($b['pinned'])) return 1;

            $ta = get_thread_last_activity_ts($a);
            $tb = get_thread_last_activity_ts($b);
            if ($ta !== $tb) {
                return $tb <=> $ta;
            }

            $ra = floatval($a['reputazione'] ?? 0.0);
            $rb = floatval($b['reputazione'] ?? 0.0);
            if ($ra !== $rb) return $rb <=> $ra;

            return strcasecmp($a['titolo'] ?? '', $b['titolo'] ?? '');
        });

        usort($userContributions, function($a, $b) {
            return strtotime($b['date']) <=> strtotime($a['date']);
        });
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>PALESTRW</title>
  <link rel="stylesheet" href="style/style_home_page.css">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
</head>
<body class="<?= $showConfirm ? 'modal-open' : '' ?>">

  <?php if ($showConfirm): ?>
    <div id="confirm-overlay"></div>
    <div id="confirm-modal">
      <h2>Successo!</h2>
      <p>La comunicazione è stata inviata.</p>
      <button id="close-confirm">Chiudi</button>
    </div>
  <?php endif; ?>

  <div class="main-header">
    <button id="hamburger" aria-label="Apri forum">☰</button>
    <div class="logo">PALESTRW</div>
    <ul class="main-menu">
      <?php if (!$id_user): ?>
        <li><a href="login.php"><img src="icon/area_riservata.png" class="icon">Area Riservata</a></li>
        <li><a href="promo.php"><img src="icon/promo.png" class="icon">Promo</a></li>
        <li><a href="corsi.php"><img src="icon/corsi.png" class="icon">Corsi</a></li>
        <li><a href="Chi_Siamo.php"><img src="icon/chi_siamo.png" class="icon">Chi Siamo</a></li>
        <li><a href="faq.php"><img src="icon/faq.png" class="icon">FAQ</a></li>
      <?php else: ?>
        <li><a href="promo.php"><img src="icon/promo.png" class="icon">Promo</a></li>
        <li><a href="corsi.php"><img src="icon/corsi.png" class="icon">Corsi</a></li>
        <li><a href="Chi_Siamo.php"><img src="icon/chi_siamo.png" class="icon">Chi Siamo</a></li>
        <li><a href="faq.php"><img src="icon/faq.png" class="icon">FAQ</a></li>
        <li><a href="profilo.php"><img src="icon/profilo.png" class="icon">Profilo</a></li>
        <li><a href="logout.php"><img src="icon/logout.png" class="icon">Logout</a></li>
      <?php endif; ?>
    </ul>
  </div>

  <div id="forum-panel" class="hidden">
    <div class="forum-header">
      <h3>Forum</h3>
      <?php if ($id_user && ($is_admin || $is_subscribed)): ?>
        <button id="toggle-history" class="rate-btn">La mia cronologia</button>
      <?php endif; ?>
    </div>
    <div class="forum-body">
      <?php if (!($is_admin || $is_subscribed)): ?>
        <p class="no-forum">Abbonamento attivo necessario per accedere al forum.</p>
      <?php elseif (empty($forumThreads)): ?>
        <p class="no-forum">Nessuna discussione disponibile. Sii il primo a creare un thread!</p>
      <?php else: ?>
        <?php
          $todayView = new DateTimeImmutable();
          foreach ($forumThreads as $thr):
            $repClass = getReputationClass($thr['reputazione']);
            $isExpanded = ($thr['id'] == $expandThreadId);
        ?>
          <div class="thread <?= $repClass ?> <?= $isExpanded ? 'expanded-thread' : '' ?> <?= $thr['pinned'] ? 'pinned-thread' : '' ?>">
            <div class="thread-header" data-id="<?= htmlspecialchars($thr['id'], ENT_QUOTES) ?>">
              <strong><?= htmlspecialchars($thr['titolo'], ENT_QUOTES) ?></strong>
              <span class="reputation-badge"><?= htmlspecialchars($thr['reputazione'], ENT_QUOTES) ?></span>

              <?php if ($thr['pinned']): ?>
                <span class="admin-indicator pinned-indicator" title="Thread pinnato">📌</span>
              <?php endif; ?>

              <?php if ($thr['locked']): ?>
                <span class="admin-indicator locked-indicator" title="Thread bloccato">🔒</span>
              <?php endif; ?>

              <span class="toggle-icon"><?= $isExpanded ? '▲' : '▼' ?></span>
            </div>
            <div class="thread-content">
              <p><?= nl2br(htmlspecialchars($thr['contenuto'], ENT_QUOTES)) ?></p>
              <small>Autore: <?= htmlspecialchars($thr['autore'], ENT_QUOTES) ?> | Data: <?= date('d/m/Y H:i', strtotime($thr['data_creazione'])) ?></small>

              <?php if ($thr['last_edit_by']): ?>
                <div class="admin-edit-note">
                  <em>Modificato da admin (<?= htmlspecialchars($thr['last_edit_by'], ENT_QUOTES) ?>)
                  il <?= date('d/m/Y H:i', strtotime($thr['last_edit_date'])) ?></em>
                </div>
              <?php endif; ?>

              <?php if ($is_admin): ?>
                <div class="admin-controls">
                  <button class="admin-btn edit" data-type="thread" data-id="<?= htmlspecialchars($thr['id'], ENT_QUOTES) ?>">Modifica</button>
                  <button class="admin-btn delete" data-type="thread" data-id="<?= htmlspecialchars($thr['id'], ENT_QUOTES) ?>">Elimina</button>
                  <button class="admin-btn pin" data-type="thread" data-id="<?= htmlspecialchars($thr['id'], ENT_QUOTES) ?>" data-status="<?= $thr['pinned'] ? '1' : '0' ?>">
                    <?= $thr['pinned'] ? 'Rimuovi pinn' : 'Pinn thread' ?>
                  </button>
                  <button class="admin-btn lock" data-type="thread" data-id="<?= htmlspecialchars($thr['id'], ENT_QUOTES) ?>" data-status="<?= $thr['locked'] ? '1' : '0' ?>">
                    <?= $thr['locked'] ? 'Sblocca thread' : 'Blocca thread' ?>
                  </button>
                </div>
              <?php endif; ?>
            </div>
            <div class="comments <?= $isExpanded ? '' : 'hidden' ?>" id="comments-<?= htmlspecialchars($thr['id'], ENT_QUOTES) ?>">
              <?php foreach ($thr['commenti'] as $cm):
                $cm_repClass = getReputationClass($cm['reputazione']);
                $likeCount = count($cm['consensi']);
                $hasLiked = $id_user && in_array($username, $cm['consensi']);
                try {
                    $commentDateObj = DateTime::createFromFormat('Y-m-d H:i:s', $cm['data']);
                    if (!$commentDateObj) $commentDateObj = new DateTime($cm['data']);
                } catch (Exception $e) {
                    $commentDateObj = new DateTime($cm['data']);
                }
                $interval = $todayView->diff($commentDateObj);
                $diffDays = $interval->days;
                if ($commentDateObj > $todayView) {
                    $diffDays = -$diffDays;
                }
              ?>
                <div class="comment <?= $cm_repClass ?>">
                  <small>
                    <em><?= htmlspecialchars($cm['autore'], ENT_QUOTES) ?></em>
                    <span class="reputation-badge"><?= htmlspecialchars($cm['reputazione'], ENT_QUOTES) ?></span>
                    <?= date('d/m H:i', strtotime($cm['data'])) ?>
                  </small>
                  <p><?= nl2br(htmlspecialchars($cm['testo'], ENT_QUOTES)) ?></p>

                  <?php if ($cm['last_edit_by']): ?>
                    <div class="admin-edit-note">
                      <em>Modificato da admin (<?= htmlspecialchars($cm['last_edit_by'], ENT_QUOTES) ?>)
                      il <?= date('d/m/Y H:i', strtotime($cm['last_edit_date'])) ?></em>
                    </div>
                  <?php endif; ?>

                  <?php if ($is_admin): ?>
                    <div class="admin-controls">
                      <button class="admin-btn edit" data-type="comment" data-id="<?= htmlspecialchars($cm['id'], ENT_QUOTES) ?>" data-thread="<?= htmlspecialchars($thr['id'], ENT_QUOTES) ?>">Modifica</button>
                      <button class="admin-btn delete" data-type="comment" data-id="<?= htmlspecialchars($cm['id'], ENT_QUOTES) ?>" data-thread="<?= htmlspecialchars($thr['id'], ENT_QUOTES) ?>">Elimina</button>
                    </div>
                  <?php endif; ?>

                  <div class="like-section">
                    <button class="like-btn <?= $hasLiked ? 'liked' : '' ?>"
                            data-thread-id="<?= htmlspecialchars($thr['id'], ENT_QUOTES) ?>"
                            data-comment-id="<?= htmlspecialchars($cm['id'], ENT_QUOTES) ?>"
                            data-comment-author="<?= htmlspecialchars($cm['autore'], ENT_QUOTES) ?>"
                            <?= $hasLiked ? 'disabled' : '' ?>>
                      👍 <span class="like-count"><?= $likeCount ?></span>
                    </button>
                    <?php if ($likeCount > 0): ?>
                      <div class="liked-users">
                        Hanno messo il pollice:
                        <?= implode(', ', array_map('htmlspecialchars', $cm['consensi'])) ?>
                      </div>
                    <?php endif; ?>
                  </div>

                  <?php if (!empty($cm['valutazioni'])): ?>
                    <div class="comment-ratings">
                      <strong>Valutazioni:</strong>
                      <ul class="ratings-list">
                        <?php foreach ($cm['valutazioni'] as $rating): ?>
                          <li>
                            <span class="rater"><?= htmlspecialchars($rating['valutatore'], ENT_QUOTES) ?>:</span>
                            Utilità: <?= htmlspecialchars($rating['utilita'], ENT_QUOTES) ?>/5,
                            Accordo: <?= htmlspecialchars($rating['accordo'], ENT_QUOTES) ?>/5
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  <?php endif; ?>

                  <?php if ($is_subscribed && $id_user && $username !== null && strcasecmp($username, $cm['autore']) !== 0 && !$thr['locked']): ?>
                    <?php if ($diffDays <= 30 && $diffDays >= 0): ?>
                      <div class="rate-comment">
                        <button class="rate-btn"
                                data-thread-id="<?= htmlspecialchars($thr['id'], ENT_QUOTES) ?>"
                                data-comment-id="<?= htmlspecialchars($cm['id'], ENT_QUOTES) ?>"
                                data-comment-author="<?= htmlspecialchars($cm['autore'], ENT_QUOTES) ?>"
                                data-days="<?= $diffDays ?>">
                          Valuta questo commento
                        </button>
                      </div>
                    <?php elseif ($diffDays < 0): ?>
                      <div class="rate-disabled">
                        <small>Impossibile valutare: la data del commento è nel futuro (<?= abs($diffDays) ?> giorni avanti).</small>
                      </div>
                    <?php else: ?>
                      <div class="rate-disabled">
                        <small>Impossibile valutare: finestra di 30 giorni scaduta (<?= $diffDays ?> giorni).</small>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>

              <?php if ($is_subscribed && !$thr['locked']): ?>
                <div class="new-comment">
                  <h4>Aggiungi commento:</h4>
                  <form method="post" action="aggiungi_messaggio_forum.php">
                    <input type="hidden" name="thread_id" value="<?= htmlspecialchars($thr['id'], ENT_QUOTES) ?>">
                    <textarea name="comment" rows="3" required placeholder="Scrivi il tuo commento..."></textarea>
                    <button type="submit" class="rate-btn">Invia</button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($is_subscribed): ?>
        <div class="new-thread">
          <h3>Crea nuova discussione</h3>
          <form method="post" action="aggiungi_messaggio_forum.php">
            <input type="text" name="titolo" placeholder="Titolo discussione" required>
            <textarea name="contenuto" rows="4" required placeholder="Contenuto della discussione..."></textarea>
            <button type="submit" class="rate-btn">Crea Discussione</button>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($id_user && ($is_admin || $is_subscribed)): ?>
        <div id="history-panel" class="hidden">
          <h4>La mia cronologia dei contributi</h4>
          <?php if (empty($userContributions)): ?>
            <p>Non hai ancora contributi nel forum.</p>
          <?php else: ?>
            <ul class="contributions-list">
              <?php foreach ($userContributions as $contribution): ?>
                <li class="contribution-item">
                  <?php if ($contribution['type'] === 'thread_created'): ?>
                    <div class="contribution-icon contribution-type-thread">📝</div>
                    <div class="contribution-content">
                      <div class="contribution-date"><?= date('d/m/Y H:i', strtotime($contribution['date'])) ?></div>
                      <div class="contribution-text">Hai creato la discussione: <strong><?= htmlspecialchars($contribution['title'], ENT_QUOTES) ?></strong></div>
                    </div>
                  <?php elseif ($contribution['type'] === 'comment_created'): ?>
                    <div class="contribution-icon contribution-type-comment">💬</div>
                    <div class="contribution-content">
                      <div class="contribution-date"><?= date('d/m/Y H:i', strtotime($contribution['date'])) ?></div>
                      <div class="contribution-text">Hai commentato: "<?= htmlspecialchars(substr($contribution['content'], 0, 80), ENT_QUOTES) ?><?= strlen($contribution['content']) > 80 ? '...' : '' ?>"</div>
                    </div>
                  <?php elseif ($contribution['type'] === 'comment_liked'): ?>
                    <div class="contribution-icon contribution-type-like">👍</div>
                    <div class="contribution-content">
                      <div class="contribution-date"><?= date('d/m/Y H:i', strtotime($contribution['date'])) ?></div>
                      <div class="contribution-text">Hai messo "mi piace" al commento di <strong><?= htmlspecialchars($contribution['comment_author'], ENT_QUOTES) ?></strong>: "<?= htmlspecialchars(substr($contribution['comment_content'], 0, 60), ENT_QUOTES) ?><?= strlen($contribution['comment_content']) > 60 ? '...' : '' ?>"</div>
                    </div>
                  <?php elseif ($contribution['type'] === 'comment_rated'): ?>
                    <div class="contribution-icon contribution-type-rating">⭐</div>
                    <div class="contribution-content">
                      <div class="contribution-date"><?= date('d/m/Y H:i', strtotime($contribution['date'])) ?></div>
                      <div class="contribution-text">Hai valutato il commento di <strong><?= htmlspecialchars($contribution['comment_author'], ENT_QUOTES) ?></strong>: Utilità <?= htmlspecialchars($contribution['utilita'], ENT_QUOTES) ?>/5, Accordo <?= htmlspecialchars($contribution['accordo'], ENT_QUOTES) ?>/5</div>
                    </div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div id="main-content" class="main-content">
    <h1 class="welcome">Benvenuti in PALESTRW</h1>
    <table class="tabella">
      <tr>
        <td><img src="img/1-quadrante.png" alt="Quadrante 1"></td>
        <td><img src="img/2-quadrante.png" alt="Quadrante 2"></td>
      </tr>
      <tr>
        <td><img src="img/3-quadrante.png" alt="Quadrante 3"></td>
        <td><img src="img/4-quadrante.png" alt="Quadrante 4"></td>
      </tr>
    </table>
  </div>

  <div class="msg-widget">
    <div class="widget-header">
      <span>Comunicazioni</span>
      <?php if ($is_admin): ?><button id="open-form">✏️</button><?php endif; ?>
    </div>
    <div class="widget-body">
      <?php if (empty($messaggi)): ?>
        <p class="no-msg">Nessuna comunicazione disponibile</p>
      <?php else: ?>
        <?php foreach ($messaggi as $msg): ?>
          <div class="widget-item">
            <strong><?= htmlspecialchars($msg['titolo'], ENT_QUOTES) ?></strong>
            <small>(<?= $msg['data_invio'] ? date('d/m H:i', strtotime($msg['data_invio'])) : '' ?>)</small>
            <p><?= nl2br(htmlspecialchars($msg['contenuto'], ENT_QUOTES)) ?></p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($is_admin): ?>
  <div id="comunicazione-modal" role="dialog" aria-hidden="true">
    <div class="modal-box" role="document">
      <span class="modal-close" id="close-comunicazione">&times;</span>
      <h3>Nuova comunicazione</h3>
      <form method="post" action="aggiungi_comunicazione.php" id="form-comunicazione">
        <label for="titolo">Titolo</label>
        <input type="text" name="titolo" id="titolo" required maxlength="200">

        <label for="contenuto">Contenuto</label>
        <textarea name="contenuto" id="contenuto" required></textarea>

        <div class="modal-actions">
          <button type="button" id="abort-comunicazione" class="rate-btn">Annulla</button>
          <button type="submit" class="rate-btn">Invia comunicazione</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div id="footer">
    <p>&copy; <?= date('Y') ?> PALESTRW. Tutti i diritti riservati.</p>
  </div>

  <script>
    const DELETE_ENDPOINT = 'elimina_contenuto.php';
    const PENALTY_COMMENT = 2.0;
    const PENALTY_THREAD  = 5.0;

    document.getElementById('close-confirm')?.addEventListener('click', () => {
      document.getElementById('confirm-modal').remove();
      document.getElementById('confirm-overlay').remove();
      document.body.classList.remove('modal-open');
      const url = new URL(window.location.href);
      url.searchParams.delete('ok');
      window.history.replaceState({}, document.title, url.toString());
    });

    const forumPanel = document.getElementById('forum-panel');
    document.getElementById('hamburger').addEventListener('click', () => {
      forumPanel.classList.toggle('hidden');
    });

    document.getElementById('toggle-history')?.addEventListener('click', function() {
      const historyPanel = document.getElementById('history-panel');
      historyPanel.classList.toggle('hidden');
      if (!historyPanel.classList.contains('hidden')) {
        historyPanel.scrollIntoView({ behavior: 'smooth' });
      }
    });

    document.querySelectorAll('.thread-header').forEach(header => {
      header.addEventListener('click', function() {
        const id = this.dataset.id;
        const box = document.getElementById('comments-' + id);
        const icon = this.querySelector('.toggle-icon');
        box.classList.toggle('hidden');
        icon.textContent = box.classList.contains('hidden') ? '▼' : '▲';
      });
    });

    document.querySelectorAll('form').forEach(form => {
      form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = 'Invio in corso...';
        }
      });
    });

    document.addEventListener('click', function(e) {
      if (e.target.closest('.like-btn')) {
        const likeBtn = e.target.closest('.like-btn');
        if (likeBtn.disabled) return;
        likeBtn.disabled = true;
        likeBtn.classList.add('liked');

        const threadId = likeBtn.dataset.threadId;
        const commentId = likeBtn.dataset.commentId;
        const likeCountEl = likeBtn.querySelector('.like-count');
        const currentCount = parseInt(likeCountEl.textContent);

        fetch('mi_piace.php', {
          method: 'POST',
          body: JSON.stringify({
            thread_id: threadId,
            comment_id: commentId,
            username: '<?= addslashes($username ?? '') ?>'
          }),
          headers: {
            'Content-Type': 'application/json',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
          }
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            likeCountEl.textContent = currentCount + 1;
            if (data.liked_users) {
              let likedUsersDiv = likeBtn.nextElementSibling;
              if (likedUsersDiv && likedUsersDiv.classList.contains('liked-users')) {
                likedUsersDiv.textContent = 'Hanno messo il pollice: ' + data.liked_users.join(', ');
              } else {
                likedUsersDiv = document.createElement('div');
                likedUsersDiv.className = 'liked-users';
                likedUsersDiv.textContent = 'Hanno messo il pollice: ' + data.liked_users.join(', ');
                likeBtn.parentNode.appendChild(likedUsersDiv);
              }
            }
          } else {
            alert('Errore: ' + data.message);
            likeBtn.disabled = false;
            likeBtn.classList.remove('liked');
          }
        })
        .catch(() => {
          alert('Si è verificato un errore');
          likeBtn.disabled = false;
          likeBtn.classList.remove('liked');
        });
      }

      const maybeRate = e.target.closest('.rate-btn');
      if (maybeRate && !maybeRate.closest('#open-form') && !maybeRate.closest('#toggle-history')) {
        if (!maybeRate.dataset || typeof maybeRate.dataset.commentId === 'undefined') {
        } else {
          const rateBtn = maybeRate;
          const threadId = rateBtn.dataset.threadId;
          const commentId = rateBtn.dataset.commentId;
          const author    = rateBtn.dataset.commentAuthor;
          const days      = parseInt(rateBtn.dataset.days ?? '9999', 10);
          if (isNaN(days)) {
            alert('Data commento non valida, impossibile valutare.');
            return;
          }
          if (days < 0) {
            alert('Impossibile valutare: la data del commento è nel futuro.');
            return;
          }
          if (days > 30) {
            alert('La finestra di valutazione (30 giorni) è scaduta per questo commento.');
            return;
          }

          const modal = document.createElement('div');
          modal.id = 'rating-modal';
          modal.innerHTML = `
            <div class="modal-content">
              <span class="close-modal">&times;</span>
              <h3>Valuta commento di ${author}</h3>
              <form id="rating-form-${threadId}-${commentId}">
                <input type="hidden" name="thread_id" value="${threadId}">
                <input type="hidden" name="comment_id" value="${commentId}">
                <div class="rating-field">
                  <label>Utilità (1-5):</label>
                  <select name="utilita" required>
                    <option value="1">1 - Poco utile</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5 - Molto utile</option>
                  </select>
                </div>
                <div class="rating-field">
                  <label>Accordo (1-5):</label>
                  <select name="accordo" required>
                    <option value="1">1 - Per niente d'accordo</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5 - Completamente d'accordo</option>
                  </select>
                </div>
                <button type="submit">Invia valutazione</button>
              </form>
            </div>
          `;
          document.body.appendChild(modal);

          modal.querySelector('.close-modal').addEventListener('click', () => modal.remove());
          modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });

          document.getElementById(`rating-form-${threadId}-${commentId}`).addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('valuta_commento.php', {
              method: 'POST',
              body: JSON.stringify({
                thread_id: formData.get('thread_id'),
                comment_id: formData.get('comment_id'),
                utilita: formData.get('utilita'),
                accordo: formData.get('accordo')
              }),
              headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
              }
            })
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                alert('Valutazione inviata con successo!');
                modal.remove();
                location.reload();
              } else {
                alert('Errore: ' + data.message);
              }
            })
            .catch(() => alert('Si è verificato un errore durante l\'invio della valutazione'));
          });
        }
      }

      if (e.target.closest('.admin-btn')) {
        const btn = e.target.closest('.admin-btn');
        const type = btn.dataset.type;
        const id = btn.dataset.id;
        const threadId = btn.dataset.thread || null;
        const action = btn.classList.contains('delete') ? 'delete' :
                      btn.classList.contains('edit') ? 'edit' :
                      btn.classList.contains('pin') ? 'pin' :
                      btn.classList.contains('lock') ? 'lock' : null;

        if (!action) return;

        if (action === 'edit') {
          let currentContent = '';
          if (type === 'thread') {
            const thread = btn.closest('.thread');
            currentContent = thread.querySelector('.thread-content p').textContent;
          } else if (type === 'comment') {
            const comment = btn.closest('.comment');
            currentContent = comment.querySelector('p').textContent;
          }

          const modal = document.createElement('div');
          modal.id = 'admin-modal';
          modal.innerHTML = `
            <div class="modal-content">
              <span class="close-modal">&times;</span>
              <h3>Modifica ${type === 'thread' ? 'thread' : 'commento'}</h3>
              <form id="edit-form-${type}-${id}">
                <input type="hidden" name="type" value="${type}">
                <input type="hidden" name="id" value="${id}">
                ${threadId ? `<input type="hidden" name="thread_id" value="${threadId}">` : ''}
                <div class="rating-field">
                  <label>Contenuto:</label>
                  <textarea name="content" rows="6" required style="width:100%">${currentContent}</textarea>
                </div>
                <button type="submit">Salva modifiche</button>
              </form>
            </div>
          `;
          document.body.appendChild(modal);

          modal.querySelector('.close-modal').addEventListener('click', () => modal.remove());
          modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });

          document.getElementById(`edit-form-${type}-${id}`).addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('modifica_contenuto.php', {
              method: 'POST',
              body: JSON.stringify({
                type: formData.get('type'),
                id: formData.get('id'),
                thread_id: formData.get('thread_id') || null,
                content: formData.get('content')
              }),
              headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
              }
            })
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                alert('Contenuto modificato con successo!');
                modal.remove();
                location.reload();
              } else {
                alert('Errore: ' + data.message);
              }
            })
            .catch(() => alert('Si è verificato un errore durante la modifica'));
          });

        } else if (action === 'delete') {
          const penalty = (type === 'thread') ? PENALTY_THREAD : PENALTY_COMMENT;
          const humanType = (type === 'thread') ? 'thread' : 'commento';
          if (!confirm(`Sei sicuro di voler eliminare questo ${humanType}?`)) return;

          const payload = {
            type: type,
            id: id,
            thread_id: threadId || null,
            rep_penalty: penalty
          };

          fetch(DELETE_ENDPOINT, {
            method: 'POST',
            body: JSON.stringify(payload),
            headers: {
              'Content-Type': 'application/json',
              'Cache-Control': 'no-cache, no-store, must-revalidate',
              'Pragma': 'no-cache',
              'Expires': '0'
            }
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              let repMessage = '';
              if (data.rep_penalty_applied) {
                const repChange = (typeof data.rep_change !== 'undefined') ? data.rep_change : null;
                if (repChange !== null) {
                  repMessage = ` Reputazione autore modificata di ${repChange} punti (base aggiornato: ${data.new_base}).`;
                }
              }
              alert((type === 'thread' ? 'Thread eliminato!' : 'Commento eliminato!') + repMessage);
              location.reload();
            } else {
              alert('Errore: ' + (data.message || 'Impossibile eliminare'));
            }
          })
          .catch(() => alert('Si è verificato un errore durante l\'eliminazione'));

        } else if (action === 'pin' || action === 'lock') {
          const status = btn.dataset.status === '1' ? 0 : 1;

          fetch('toggle_admin.php', {
            method: 'POST',
            body: JSON.stringify({
              type: action,
              id: id,
              status: status
            }),
            headers: {
              'Content-Type': 'application/json',
              'Cache-Control': 'no-cache, no-store, must-revalidate',
              'Pragma': 'no-cache',
              'Expires': '0'
            }
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              alert(
                action === 'pin'
                  ? (status ? 'Thread pinnato!' : 'Thread rimosso dai pinnati!')
                  : (status ? 'Thread bloccato!' : 'Thread sbloccato!')
              );
              location.reload();
            } else {
              alert('Errore: ' + data.message);
            }
          })
          .catch(() => alert('Si è verificato un errore'));
        }
      }
    });

    <?php if ($expandThreadId): ?>
      document.addEventListener('DOMContentLoaded', function() {
        const threadId = '<?= htmlspecialchars($expandThreadId, ENT_QUOTES) ?>';
        forumPanel.classList.remove('hidden');
        const threadHeader = document.querySelector(`.thread-header[data-id="${threadId}"]`);
        if (threadHeader) {
          const threadElement = threadHeader.closest('.thread');
          threadElement.classList.add('highlighted');
          setTimeout(() => {
            threadElement.classList.remove('highlighted');
          }, 3000);
          threadElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    <?php endif; ?>

    (function(){
      const openBtn = document.getElementById('open-form');
      const modal = document.getElementById('comunicazione-modal');
      const closeBtn = document.getElementById('close-comunicazione');
      const abortBtn = document.getElementById('abort-comunicazione');

      openBtn?.addEventListener('click', function(){
        if (modal) {
          modal.style.display = 'flex';
          modal.setAttribute('aria-hidden','false');
          document.body.classList.add('modal-open');
        }
      });

      function closeModal() {
        if (modal) {
          modal.style.display = 'none';
          modal.setAttribute('aria-hidden','true');
          document.body.classList.remove('modal-open');
          document.getElementById('form-comunicazione')?.reset();
        }
      }

      closeBtn?.addEventListener('click', closeModal);
      abortBtn?.addEventListener('click', closeModal);
      modal?.addEventListener('click', function(e){
        if (e.target === modal) closeModal();
      });
    })();
  </script>
</body>
</html>
