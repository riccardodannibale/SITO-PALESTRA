<?php
session_start();
include __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['ok' => false];

if (empty($_SESSION['id_user'])) {
    echo json_encode($response);
    exit;
}

$me = (int) $_SESSION['id_user'];

$is_admin = false;
$username = null;
if ($stmt = $conn->prepare("SELECT is_admin, username FROM users WHERE id_user = ?")) {
    $stmt->bind_param('i', $me);
    $stmt->execute();
    $stmt->bind_result($is_admin_flag, $username_res);
    if ($stmt->fetch()) {
        $is_admin = ((int)$is_admin_flag === 1);
        $username = $username_res;
    }
    $stmt->close();
}

$xmlPath = __DIR__ . '/xml/private_chat.xml';
if (!file_exists($xmlPath)) {
    if ($is_admin) {
        echo json_encode(['ok' => true, 'unread_by_user' => (object)[]]);
    } else {
        echo json_encode(['ok' => true, 'unread_count' => 0]);
    }
    exit;
}

libxml_use_internal_errors(true);
$xml = simplexml_load_file($xmlPath);
if ($xml === false) {
    if ($is_admin) {
        echo json_encode(['ok' => true, 'unread_by_user' => (object)[]]);
    } else {
        echo json_encode(['ok' => true, 'unread_count' => 0]);
    }
    exit;
}

if ($is_admin) {
    $unread = [];
    foreach ($xml->messaggio as $m) {
        $attr = $m->attributes();
        $dest = (string)$attr['destinatario'];
        $read = (string)$attr['risposta'];

        if ($read === '0' && $dest === 'admin') {
            $mitt = (string)$attr['mittente'];
            
            $uid = null;
            if (ctype_digit($mitt)) {
                $uid = (int)$mitt;
            } else {
                $stmt = $conn->prepare("SELECT id_user FROM users WHERE username = ? LIMIT 1");
                $stmt->bind_param('s', $mitt);
                $stmt->execute();
                $stmt->bind_result($foundId);
                if ($stmt->fetch()) $uid = (int)$foundId;
                $stmt->close();
            }
            
            if ($uid > 0) {
                $unread[$uid] = ($unread[$uid] ?? 0) + 1;
            }
        }
    }
    echo json_encode(['ok' => true, 'unread_by_user' => $unread]);
    exit;

} else {
    $count = 0;
    foreach ($xml->messaggio as $m) {
        $attr = $m->attributes();
        $mitt = (string)$attr['mittente'];
        $dest = (string)$attr['destinatario'];
        $read = (string)$attr['risposta'];

        if ($read === '0' && $mitt === 'admin' && $dest === $username) {
            $count++;
        }
    }
    echo json_encode(['ok' => true, 'unread_count' => $count]);
    exit;
}
?>