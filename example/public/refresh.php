<?php
require 'config.php';

$uac = UacClient::instance();

try {
    $new = $uac->grantRefreshToken($uac->getAccessToken());
    $uac->setAccessToken($new);
    header('Location: /');
    exit();
} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
    echo "<pre>" . print_r($e->getResponseBody(), true) . "</pre>";

    echo '<p><a href="index.php">Index</a></p>';
}


