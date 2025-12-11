<?php
require 'config.php';

$uac = UacClient::instance()
    // Поставим флаг, что открыто в поп-апе
    ->setRunInPopup(isset($_REQUEST['popup']))
    // После авторизации вернем пользователя туда, откуда он пришел
    ->setReturnPath($_SERVER['HTTP_REFERER'])
//    ->setPrompt('consent')
    ->setLocale('ru');

$uac->setAuthorizationHint('Добро пожаловать');
//$uac->setScope(['gmobile']);

//https://pass.fc-zenit.ru/auth?response_type=code&client_id=9dcec76c-89a8-40a2-bc69-e4c67dadf837&scope=gmobile&prompt=consent&redirect_uri=https%3A%2F%2Fpreprod.gid-auth.ru%2Fauth%2Fzenit&state=84b9746662ad2673dda5828ac2035f515579d0bbf55726
//https://pass.fc-zenit.ru/auth?response_type=code&client_id=9dcec76c-89a8-40a2-bc69-e4c67dadf837&scope=user_read%20gmobile&prompt=consent&redirect_uri=https%3A%2F%2Fstage-auth.k8s-dev.gid.team%2Fauth%2Fzenit&state=cd5a7386e889298eb71d1271e6ecfc9e19ef9a635f1735
//$uac->setPrompt('none');

if (getenv('WEBHOOK_URI')) {
    $uac->setWebhook(getenv('WEBHOOK_URI') . '?time=' . time());
}

$location = $uac->getAuthorizationUrl();
// Отправляем пользователя на сервер за авторизацией
header('Location: ' . $location);

exit;
