<?php
require 'config.php';

$uac = UacClient::instance();

// Поставим флаг, что открыто в поп-апе
$uac->setRunInPopup(isset($_REQUEST['popup']));

// Отправляем пользователя на сервер за авторизацией
header('Location: ' . $uac->getAuthorizationUrl($_SERVER['HTTP_REFERER']));

exit;
