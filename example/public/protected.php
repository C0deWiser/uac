<?php
require 'config.php';

// Это пример того, как можно одной строкой защищать странцы от неавторизованного доступа.

UacClient::instance()->requireAuthorization($_SERVER['REQUEST_URI']);

?>
<p>User is authorized</p>

<p><a href="/">Main page</a></p>

<?php

echo "<pre>" . print_r(UacClient::instance()->getResourceOwner()->toArray(), true) . "</pre>";
