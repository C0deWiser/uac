<?php
require 'config.php';

UacClient::instance()->requireAuthorization($_SERVER['REQUEST_URI']);

?>
<p>User is authorized</p>

<p><a href="/">Main page</a></p>
