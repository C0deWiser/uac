<?php
require 'config.php';

$uac = UacClient::instance()
    // Поставим флаг, что открыто в поп-апе
    ->setRunInPopup(isset($_REQUEST['popup']))
    // После авторизации вернем пользователя туда, откуда он пришел
    ->setReturnPath($_SERVER['HTTP_REFERER'])
    ->setPrompt('consent')
    ->setLocale('ru');

//$uac->setAuthorizationHint('Добро пожаловать');

//$uac->setPrompt('none');

if (getenv('WEBHOOK_URI')) {
    $uac->setWebhook(getenv('WEBHOOK_URI') . '?time=' . time());
}

$location = $uac->getAuthorizationUrl();
// Отправляем пользователя на сервер за авторизацией
header('Location: ' . $location);

exit;
