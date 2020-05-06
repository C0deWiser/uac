<?php
require '../config.php';

$uac = UacClient::instance();
$uac->requireAuthorization($_SERVER['REQUEST_URI']);
$office = $uac->getOnlineOffice();

echo $office->assetHtml();
echo $office->assetStyles();
echo $office->assetJQuery();
echo $office->assetScripts();