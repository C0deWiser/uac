<?php
require 'config.php';

$uac = UacClient::instance();

// Поставим флаг, что открыто в поп-апе
$uac->setRunInPopup(isset($_REQUEST['popup']));

// После авторизации вернем пользователя туда, откуда он пришел
$uac->setReturnPath($_SERVER['HTTP_REFERER']);

$uac->setLocale('ru');

$uac->setAuthorizationHint('Добро пожаловать');

//$uac->setPrompt('none');

if (getenv('WEBHOOK_URI')) {
    $uac->setWebhook(getenv('WEBHOOK_URI') . '?time=' . time());
}

// Отправляем пользователя на сервер за авторизацией
header('Location: ' . $uac->getAuthorizationUrl());

exit;
