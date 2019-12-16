<?php
require 'config.php';

$uac = UacClient::instance();
$uac->setRunInPopup(isset($_REQUEST['popup']));
$uac->startAuthorization($_SERVER['HTTP_REFERER']);
