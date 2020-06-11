<?php
require '../config.php';

$uac = UacClient::instance();
$uac->requireAuthorization($_SERVER['REQUEST_URI']);

$office = $uac
    ->setLocale('en')
    ->getOnlineOffice('http://localhost:8080/sign-out.php');

echo $office->assetHtml();
echo $office->assetStyles();
echo $office->assetJQuery();
echo $office->assetScripts();