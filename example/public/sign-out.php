<?php
require 'config.php';

$uac = UacClient::instance();

header('Location: ' . $uac->getDeauthorizationUrl($_SERVER['HTTP_REFERER']));

exit;