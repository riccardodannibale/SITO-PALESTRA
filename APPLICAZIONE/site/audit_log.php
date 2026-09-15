<?php
function log_admin_action($action, $target_type, $target_id, $details = []) {
    $logFile = __DIR__ . '/xml/admin_audit.xml';
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;

    if (!file_exists($logFile)) {
        $root = $dom->createElement('admin_actions');
        $dom->appendChild($root);
        $dom->save($logFile);
    }

    $dom->load($logFile);
    $root = $dom->documentElement;

    $actionElement = $dom->createElement('action');
    $actionElement->setAttribute('timestamp', date('Y-m-d H:i:s'));
    $actionElement->setAttribute('admin', $_SESSION['username']);
    $actionElement->setAttribute('type', $action);
    $actionElement->setAttribute('target_type', $target_type);
    $actionElement->setAttribute('target_id', $target_id);

    foreach ($details as $key => $value) {
        $detail = $dom->createElement('detail', htmlspecialchars($value));
        $detail->setAttribute('name', $key);
        $actionElement->appendChild($detail);
    }

    $root->appendChild($actionElement);
    $dom->save($logFile);
}
?>