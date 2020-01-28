<?php
/**
 * Created by PhpStorm.
 * User: amikhailov
 * Date: 22.08.2019
 * Time: 12:51
 */

use Codewiser\UAC\Logger;

require 'config.php';

$uac = UacClient::instance();

try {

    $uac->callbackController($_GET, 'phone');

    // Если пользователь откуда-то пришел, то пусть идет обратно
    // Если мы были в popup, то закроем его
    if (!$uac->closePopup()) {
        header('Location: ' . $uac->getReturnPath('/'));
        exit();
    }

} catch (\Codewiser\UAC\Exception\OauthResponseException $e) {

    if ($e->getMessage() == 'access_denied') {
        // Авторизацию прервал сам пользователь
        // Поэтому не считаем это ошибкой
        if (!$uac->closePopup()) {
            header('Location: ' . $uac->getReturnPath('/'));
            exit();
        }
    }

    echo "Error {$e->getCode()}: {$e->getMessage()}";
    if ($e->getDescription()) {
        echo "<p>{$e->getDescription()}</p>";
    }
    if ($e->getUri()) {
        echo '<a href="' . $e->getUri() . '">' . $e->getUri() . '</a>';
    }
    echo "<pre>{$e->getResponseBody()}</pre>";
    die();
} catch (Exception $e) {
    echo "Error {$e->getCode()}: {$e->getMessage()}";
    die();
}
