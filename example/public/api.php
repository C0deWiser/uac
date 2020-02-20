<?php
require 'config.php';

$uac = UacClient::instance();

try {
    $info = $uac->apiRequestAuthorize(getallheaders(), $_REQUEST);

    if (!$info->hasScope('read')) {
        throw new \Codewiser\UAC\Exception\Api\InsufficientScopeException('test');
    }

    if (!isset($_REQUEST['test'])) {
        throw new \Codewiser\UAC\Exception\Api\InvalidRequestException("Missing 'test' parameter");
    }

    echo 'ok';

} catch (\Codewiser\UAC\Exception\Api\RequestException $e) {
    $uac->apiRespondWithError($e);
}

exit;
