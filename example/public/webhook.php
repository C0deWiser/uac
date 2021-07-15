<?php
require 'config.php';

$uac = UacClient::instance();

$uac->log('webhook', (array)getallheaders());