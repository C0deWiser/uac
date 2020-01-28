<?php
require 'config.php';

$uac = UacClient::instance();

if (isset($_REQUEST['both'])) {
    // Отправляем пользователя на сервер, чтобы он там деавторизовался.
    // В колбеке деавторизуем локально.
    header('Location: ' . $uac->getDeauthorizationUrl($_SERVER['HTTP_REFERER']));
} else {
    // Просто деавторизуем локально пользователя,
    // И, конечно, забудем полученный токен
    $uac->deauthorizeResourceOwner();
    header('Location: /');
}

exit;