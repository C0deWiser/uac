<?php
require 'config.php';

$uac = UacClient::instance();

$uac->log('webhook', (array)getallheaders() + (array)$_GET);

$request = $uac->apiRequest(getallheaders(), $_REQUEST);

try {
    $request->validate();
    $uac->log($request->token());
} catch (\Codewiser\UAC\Exception\Api\RequestException $e) {
    $uac->log($e->getMessage());
}