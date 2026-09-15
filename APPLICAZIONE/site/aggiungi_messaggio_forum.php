<?php
session_start();

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php');
    exit;
}

$backupBaseDir = __DIR__ . '/backup';

function makeBackup(string $filePath, string $backupBaseDir): bool {
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
    if (!copy($filePath, $backupName)) {
        error_log("Backup fallito per $filePath in $backupName");
        return false;
    }
    return true;
}

$is_subscribed = false;
$xmlAb = __DIR__ . '/xml/user_abbonamenti.xml';

if (file_exists($xmlAb)) {
    $abDoc = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$abDoc->load($xmlAb)) {
        makeBackup($xmlAb, $backupBaseDir);
        $abDoc = new DOMDocument();
        $rootAb = $abDoc->createElement('abbonamenti');
        $abDoc->appendChild($rootAb);
    }
    $today = new DateTimeImmutable();
    foreach ($abDoc->getElementsByTagName('abbonamento_utente') as $ab) {
        $uid = $ab->getElementsByTagName('id_user')->item(0)->textContent ?? '';
        $stato = $ab->getElementsByTagName('stato')->item(0)->textContent ?? '';
        $inizioNode = $ab->getElementsByTagName('data_inizio')->item(0);
        $scadeNode = $ab->getElementsByTagName('data_scadenza')->item(0);
        if (!$inizioNode || !$scadeNode) {
            continue;
        }
        try {
            $inizio = new DateTimeImmutable($inizioNode->textContent);
            $scade = new DateTimeImmutable($scadeNode->textContent);
        } catch (Exception $e) {
            continue;
        }
        $scade_end = $scade->modify('+1 day')->setTime(0,0);
        if ($uid == $_SESSION['id_user'] && $stato === 'attivo' && $today >= $inizio && $today < $scade_end) {
            $is_subscribed = true;
            break;
        }
    }
}

if (!$is_subscribed) {
    header('Location: home_page.php');
    exit;
}

$xmlFile = __DIR__ . '/xml/forum.xml';
libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->preserveWhiteSpace = false;
$doc->formatOutput = true;

if (file_exists($xmlFile)) {
    if (!$doc->load($xmlFile)) {
        makeBackup($xmlFile, $backupBaseDir);
        $doc = new DOMDocument();
        $root = $doc->createElement('forum');
        $doc->appendChild($root);
    }
} else {
    $root = $doc->createElement('forum');
    $doc->appendChild($root);
}

if (!empty($_POST['titolo'])) {
    $titolo_raw = trim($_POST['titolo']);
    $contenuto_raw = trim($_POST['contenuto'] ?? '');
    if ($titolo_raw === '' || $contenuto_raw === '') {
        header('Location: home_page.php');
        exit;
    }
    $threadId = 'thread_' . uniqid();
    $thread = $doc->createElement('thread');
    $thread->setAttribute('id', $threadId);

    $tit = $doc->createElement('titolo');
    $tit->appendChild($doc->createTextNode($titolo_raw));
    $aut = $doc->createElement('autore');
    $aut->appendChild($doc->createTextNode($_SESSION['username']));
    $data = $doc->createElement('data_creazione');
    $data->appendChild($doc->createTextNode(date('Y-m-d H:i:s')));
    $cont = $doc->createElement('contenuto');
    $cont->appendChild($doc->createTextNode($contenuto_raw));
    $commenti = $doc->createElement('commenti');

    $thread->appendChild($tit);
    $thread->appendChild($aut);
    $thread->appendChild($data);
    $thread->appendChild($cont);
    $thread->appendChild($commenti);
    $doc->documentElement->appendChild($thread);

    $redirectId = $threadId;
} else {
    $threadId = $_POST['thread_id'] ?? null;
    $comment_raw = trim($_POST['comment'] ?? '');
    if (!$threadId || $comment_raw === '') {
        header('Location: home_page.php');
        exit;
    }
    $thread = null;
    foreach ($doc->getElementsByTagName('thread') as $t) {
        if ($t->hasAttribute('id') && $t->getAttribute('id') === (string)$threadId) {
            $thread = $t;
            break;
        }
    }
    if (!$thread) {
        header('Location: home_page.php');
        exit;
    }

    $commenti = $thread->getElementsByTagName('commenti')->item(0);
    if (!$commenti) {
        $commenti = $doc->createElement('commenti');
        $thread->appendChild($commenti);
    }

    $commento = $doc->createElement('commento');
    $commento->setAttribute('id', 'comm_' . uniqid());
    $autore = $doc->createElement('autore');
    $autore->appendChild($doc->createTextNode($_SESSION['username']));
    $cont = $doc->createElement('contenuto');
    $cont->appendChild($doc->createTextNode($comment_raw));
    $data = $doc->createElement('data');
    $data->appendChild($doc->createTextNode(date('Y-m-d H:i:s')));
    $consensi = $doc->createElement('consensi');
    $valutazioni = $doc->createElement('valutazioni_commento');

    $commento->appendChild($autore);
    $commento->appendChild($cont);
    $commento->appendChild($data);
    $commento->appendChild($consensi);
    $commento->appendChild($valutazioni);
    $commenti->appendChild($commento);

    $redirectId = $threadId;
}

if (file_exists($xmlFile)) {
    makeBackup($xmlFile, $backupBaseDir);
}

$tmp = $xmlFile . '.tmp';
$xmlString = $doc->saveXML();
if (file_put_contents($tmp, $xmlString, LOCK_EX) === false) {
    error_log("Scrittura su tmp fallita: $tmp");
    if (file_put_contents($xmlFile, $xmlString, LOCK_EX) === false) {
        error_log("Scrittura diretta su $xmlFile fallita");
    } else {
        @chmod($xmlFile, 0666);
    }
} else {
    if (!rename($tmp, $xmlFile)) {
        error_log("Rename da $tmp a $xmlFile fallito");
        if (file_put_contents($xmlFile, $xmlString, LOCK_EX) === false) {
            error_log("Fallback scrittura diretta su $xmlFile fallita");
        } else {
            @chmod($xmlFile, 0666);
        }
        @unlink($tmp);
    } else {
        @chmod($xmlFile, 0666);
    }
}

header("Location: home_page.php?thread_id=" . urlencode($redirectId));
exit;
