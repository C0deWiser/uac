<?php
/**
 * Created by PhpStorm.
 * User: amikhailov
 * Date: 22.08.2019
 * Time: 12:54
 */

session_start();

error_reporting(E_ALL);
ini_set('display_errors', 'on');

$variables = (array)include __DIR__ . '/../.env.php';

foreach ($variables as $key => $value) {
    putenv("$key=$value");
}

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../UacClient.php';
require __DIR__ . '/../Cache.php';

//ini_set('display_errors', getenv('APP_DEBUG') ? 'on' : 'off');
