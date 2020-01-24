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

    if (!$uac->hasAccessToken()) {
        $uac->callbackController($_GET, 'phone');
    }

    // Если пользователь откуда-то пришел, то пусть идет обратно
    // Если мы были в popup, то закроем его
    echo "<script>window.close();</script>";

    header('Location: ' . $uac->getReturnPath('/'));
    exit();

} catch (\Codewiser\UAC\Exception\IdentityProviderException $e) {
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
