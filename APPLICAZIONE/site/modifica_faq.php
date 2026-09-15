<?php
session_start();
if (!isset($_SESSION['id_user']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$xmlFile = 'xml/faq.xml';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['edit_id'] ?? '';
    $question = $_POST['edit_question'] ?? '';
    $answer = $_POST['edit_answer'] ?? '';

    if (!empty($id) && !empty($question) && !empty($answer)) {
        $xml = simplexml_load_file($xmlFile);
        $found = false;

        foreach ($xml->faq as $faq) {
            if ((string)$faq->id === $id) {
                $faq->question = htmlspecialchars($question);
                $faq->answer = htmlspecialchars($answer);
                $found = true;
                break;
            }
        }

        if ($found) {
            $xml->asXML($xmlFile);
            header('Location: faq.php?status=success&message=FAQ modificata con successo');
        } else {
            header('Location: faq.php?status=error&message=FAQ non trovata');
        }
    } else {
        header('Location: faq.php?status=error&message=Campi mancanti');
    }
} else {
    header('Location: faq.php');
}
?>