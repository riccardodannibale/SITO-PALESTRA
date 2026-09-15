<?php
session_start();
if (!isset($_SESSION['id_user']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$xmlFile = 'xml/faq.xml';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = $_POST['new_question'] ?? '';
    $answer = $_POST['new_answer'] ?? '';

    if (!empty($question) && !empty($answer)) {
        $xml = simplexml_load_file($xmlFile);

        $max_id = 0;
        foreach ($xml->faq as $faq) {
            $id = (int)$faq->id;
            if ($id > $max_id) $max_id = $id;
        }
        $new_id = $max_id + 1;

        $new_faq = $xml->addChild('faq');
        $new_faq->addChild('id', $new_id);
        $new_faq->addChild('question', htmlspecialchars($question));
        $new_faq->addChild('answer', htmlspecialchars($answer));

        $xml->asXML($xmlFile);
        header('Location: faq.php?status=success&message=FAQ aggiunta con successo');
    } else {
        header('Location: faq.php?status=error&message=Campi mancanti');
    }
} else {
    header('Location: faq.php');
}
?>