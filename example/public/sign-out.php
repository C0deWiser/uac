<?php
require 'config.php';

$uac = UacClient::instance();

if (isset($_REQUEST['both'])) {

    // После деавторизации вернем пользователя туда, откуда он пришел
    $uac->setReturnPath($_SERVER['HTTP_REFERER']);

    // Отправляем пользователя на сервер, чтобы он там деавторизовался
    // В колбеке деавторизуем локально
    header('Location: ' . $uac->getDeauthorizationUrl());
} else {
    // Просто деавторизуем локально пользователя, забудем токен
    $uac->unsetAccessToken();
    header('Location: /');
}

exit;