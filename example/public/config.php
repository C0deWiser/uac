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

$variables = (array) include '../.env.php';

foreach ($variables as $key => $value) {
    putenv("$key=$value");
}

require '../../vendor/autoload.php';
require '../UacClient.php';
require '../Context.php';
$classLoader = new \Composer\Autoload\ClassLoader();
$classLoader->addPsr4("Test\\", 'tests/Test', true);
$classLoader->register();

//ini_set('display_errors', getenv('APP_DEBUG') ? 'on' : 'off');
