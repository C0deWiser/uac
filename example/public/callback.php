<?php
/**
 * Created by PhpStorm.
 * User: amikhailov
 * Date: 22.08.2019
 * Time: 12:51
 */

use Codewiser\UAC\Logger;

require 'config.php';

//Logger::instance()->info('Callback', ['request'=>$_REQUEST, 'page'=>$_SERVER['REQUEST_URI']]);

$uac = UacClient::instance();

try {

    if (!$uac->hasAccessToken()) {
        $uac->callbackController($_GET, 'phone');
    }

    // Если пользователь откуда-то пришел, то пусть идет обратно
    $uac->finishOauthProcess('/');

} catch (\UAC\Exception\IdentityProviderException $e) {
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
