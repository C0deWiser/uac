<?php
require 'config.php';
$uac = UacClient::instance();
$uac->startDeauthorization($_SERVER['HTTP_REFERER']);