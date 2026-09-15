<?php
session_start();
include __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$result = ['ok' => false, 'changed' => 0];

if (empty($_SESSION['id_user'])) {
    echo json_encode($result);
    exit;
}

$me = (int) $_SESSION['id_user'];

$is_admin = false;
if ($stmt = $conn->prepare("SELECT is_admin FROM users WHERE id_user = ?")) {
    $stmt->bind_param('i', $me);
    $stmt->execute();
    $stmt->bind_result($is_admin_flag);
    if ($stmt->fetch()) $is_admin = ((int)$is_admin_flag === 1);
    $stmt->close();
}

$admin_ids = [];
$res = $conn->query("SELECT id_user FROM users WHERE is_admin = 1");
while ($row = $res->fetch_assoc()) {
    $admin_ids[] = (string)$row['id_user'];
}

$xmlPath = __DIR__ . '/xml/private_chat.xml';
if (!file_exists($xmlPath)) {
    echo json_encode($result);
    exit;
}

$doc = new DOMDocument();
$doc->preserveWhiteSpace = false;
$doc->formatOutput = true;
libxml_use_internal_errors(true);
$doc->load($xmlPath);
$xp = new DOMXPath($doc);

$changed = 0;

if ($is_admin && isset($_GET['user_id'])) {
    $target = (int) $_GET['user_id'];

    $nodes = $xp->query("//messaggio[@mittente='{$target}' and @destinatario='{$me}' and @risposta='0']");
    foreach ($nodes as $n) {
        $n->setAttribute('risposta', '1');
        $changed++;
    }

    if ($changed === 0) {
        if ($stmt = $conn->prepare("SELECT username FROM users WHERE id_user = ? LIMIT 1")) {
            $stmt->bind_param('i', $target);
            $stmt->execute();
            $stmt->bind_result($username);
            if ($stmt->fetch() && $username) {
                $escaped = htmlspecialchars($username, ENT_QUOTES | ENT_XML1);
                $nodes2 = $xp->query("//messaggio[@mittente='{$escaped}' and @destinatario='{$me}' and @risposta='0']");
                foreach ($nodes2 as $n2) {
                    $n2->setAttribute('risposta', '1');
                    $changed++;
                }
            }
            $stmt->close();
        }
    }
}

if (!$is_admin) {
    foreach ($admin_ids as $admin_id) {
        $nodes = $xp->query("//messaggio[@mittente='{$admin_id}' and @destinatario='{$me}' and @risposta='0']");
        foreach ($nodes as $n) {
            $n->setAttribute('risposta', '1');
            $changed++;
        }
    }
}

if ($changed > 0) {
    $tmp = $xmlPath . '.tmp';
    $doc->save($tmp);
    if (@rename($tmp, $xmlPath)) {
        $result['ok'] = true;
        $result['changed'] = $changed;
    } else {
        if ($doc->save($xmlPath) !== false) {
            $result['ok'] = true;
            $result['changed'] = $changed;
        }
    }
} else {
    $result['ok'] = true;
    $result['changed'] = 0;
}

echo json_encode($result);
exit;
