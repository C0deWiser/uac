<?php
require 'config.php';
$uac = UacClient::instance();

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UAC</title>
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"
            integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo=" crossorigin="anonymous"></script>
    <script src="/assets/js/oauth.js"></script>
</head>
<body>
<?php

// Restore client access_token from cache

/** @var \League\OAuth2\Client\Token\AccessTokenInterface|null $at */
$at = null;

if ($at) {
    $at = $uac->refreshAccessTokenIfRequired($at);
}
if (!$at) {
    $at = $uac->grantClientCredentials();
}
// Now store client access_token to the cache


$response = $uac->provider->fetchResource('GET', '/api/loyalty-program/discount-levels', $at,
    [
        'headers' => [
            'Accept' => 'application/json'
        ],
        'body' => ''
    ]
);
echo "<pre>" . print_r($response, true) . "</pre>";

if ($uac->hasAccessToken()) {
    ?>
    <p><a href="sign-out.php">Sign-out local (keep server authorization)</a></p>
    <p><a href="sign-out.php?both">Sign-out local and from server</a></p>
    <p><a href="protected.php">Protected page (Authorization required)</a></p>
    <p><a href="elk">ELK</a></p>
    <?php
    echo 'Access Token ' . $uac->getAccessToken()->getToken() . '<br>';
    echo "<pre>" . print_r($uac->introspectToken($uac->getAccessToken())->toArray(), true) . "</pre>";

    $user = $uac->setLocale('en')->getResourceOwner();

    if ($user->picture) {
        echo "<img src='{$user->picture}' alt='' width='128'>";
    }
    echo "<pre>" . print_r($user->toArray(), true) . "</pre>";
    echo "<pre>" . print_r($user->rules()->toArray(), true) . "</pre>";

} else {
    ?>
    <p><a href="sign-in.php">Authorize fullscreen</a></p>
    <p><a href="sign-in.php?popup"
          onclick="oauth(this.href, function() {location.href = location.href}); return false;">Authorize in Popup</a>
    </p>
    <p><a href="protected.php">Authorize on demand</a></p>
    <p><a href="elk">ELK</a></p>
    <?php
}
?>
</body>
</html>
