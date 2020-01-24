<?php
require 'config.php';

$uac = UacClient::instance();
$uac->setRunInPopup(isset($_REQUEST['popup']));

header('Location: ' . $uac->getAuthorizationUrl($_SERVER['HTTP_REFERER']));

exit;
