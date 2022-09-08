<?php
require 'config.php';

$uac = UacClient::instance();

$uac->logger()->debug('webhook', (array)getallheaders() + (array)$_GET);

$request = $uac->apiRequest(getallheaders(), $_REQUEST);

try {
    $request->validate();
    $uac->logger()->debug($request->token(), $request->introspect()->toArray());
} catch (\Codewiser\UAC\Exception\Api\RequestException $e) {
    $uac->logger()->warning($e->getMessage());
}
