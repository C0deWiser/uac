<?php

namespace Codewiser\UAC;

use Monolog\Handler\StreamHandler;

class Logger
{
    public static function instance()
    {
        $log = new \Monolog\Logger('uac');
        $log->pushHandler(new StreamHandler('logs/uac.log', \Monolog\Logger::DEBUG));
        return $log;
    }
}
