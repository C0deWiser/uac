<?php
require 'config.php';

$uac = UacClient::instance();

try {

    $request = $uac->apiRequest(getallheaders(), $_REQUEST);

    $request->validate();

    $request->authorize();

    $info = $request->introspect();

    echo "<pre>" . print_r($info->toArray(), true) . "</pre>";

    if (!$info->hasScope('read')) {
        throw new \Codewiser\UAC\Exception\Api\InsufficientScopeException('test');
    }

    if (!isset($_REQUEST['test'])) {
        throw new \Codewiser\UAC\Exception\Api\InvalidRequestException("Missing 'test' parameter");
    }

    $user = $request->user();

    echo 'ok';

} catch (\Codewiser\UAC\Exception\Api\RequestException $e) {
    $request->respondWithError($e);
}

exit;
