<?php
session_start();
if (!isset($_SESSION['id_user']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$xmlFile = 'xml/faq.xml';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['delete_id'] ?? '';

    if (!empty($id)) {
        $xml = simplexml_load_file($xmlFile);
        $found = false;
        $index = 0;

        foreach ($xml->faq as $faq) {
            if ((string)$faq->id === $id) {
                unset($xml->faq[$index]);
                $found = true;
                break;
            }
            $index++;
        }

        if ($found) {
            $xml->asXML($xmlFile);
            header('Location: faq.php?status=success&message=FAQ eliminata con successo');
        } else {
            header('Location: faq.php?status=error&message=FAQ non trovata');
        }
    } else {
        header('Location: faq.php?status=error&message=ID mancante');
    }
} else {
    header('Location: faq.php');
}
?>