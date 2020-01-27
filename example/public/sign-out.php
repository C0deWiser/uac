<?php
require 'config.php';

if (isset($_REQUEST['both'])) {
    $uac = UacClient::instance();
    header('Location: ' . $uac->getDeauthorizationUrl($_SERVER['HTTP_REFERER']));
} else {
    $context = new \Codewiser\UAC\ContextManager();
    unset($context->access_token);
    header('Location: /');
}

exit;