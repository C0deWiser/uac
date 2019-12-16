<?php

namespace Codewiser\UAC;

class StateContext
{

    private static $instance;

    const PREFIX = 'oauth2';

    const KEYS = [
        'state',
        'response_type',
        'return_path',
        'popup'
    ];

    /**
     * @return StateContext
     */
    public static function getInstance()
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::getInstance() instead
     */
    private function __construct()
    {
    }

    /**
     * prevent the instance from being cloned (which would create a second instance of it)
     */
    private function __clone()
    {
    }

    /**
     * prevent from being unserialized (which would create a second instance of it)
     */
    private function __wakeup()
    {
    }

    /**
     * @param $key
     * @return string
     */
    public static function getKey($key)
    {
        return self::PREFIX . $key;
    }

    /**
     *
     */
    public static function forget()
    {
        foreach (self::KEYS as $KEY) {
            if (isset($_SESSION[self::getKey($KEY)])) {
                unset($_SESSION[self::getKey($KEY)]);
            }
        }
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        $a = [];

        foreach (self::KEYS as $KEY) {
            if (isset($_SESSION[self::getKey($KEY)])) {
                $a[$KEY] = $_SESSION[self::getKey($KEY)];
            }
        }

        return $a;
    }

    public function setResponseTypeCode()
    {
        $_SESSION[self::getKey('response_type')] = 'code';
    }


    public function setResponseTypeLeave()
    {
        $_SESSION[self::getKey('response_type')] = 'leave';
    }

    /**
     * @return bool
     */
    public function isResponseTypeCode()
    {
        return @$_SESSION[self::getKey('response_type')] == 'code' ? true : false;
    }

    /**
     * @return bool
     */
    public function isResponseTypeLeave()
    {
        return @$_SESSION[self::getKey('response_type')] == 'leave' ? true : false;
    }

    /**
     * @return mixed
     */
    public function getReturnPath()
    {
        return @$_SESSION[self::getKey('return_path')];
    }

    /**
     * @param mixed $return_path
     */
    public function setReturnPath($return_path)
    {
        $_SESSION[self::getKey('return_path')] = $return_path;
    }

    public function is($state)
    {
        return @$_SESSION[self::getKey('state')] && $state && @$_SESSION[self::getKey('state')] == $state;
    }

    /**
     * @return null
     */
    public function getState()
    {
        return @$_SESSION[self::getKey('state')];
    }

    public function setState($state)
    {
        $_SESSION[self::getKey('state')] = $state;
    }

    /**
     * @return bool
     */
    public function isPopup()
    {
        return @$_SESSION[self::getKey('popup')];
    }

    /**
     * @param bool $popup
     */
    public function setPopup($popup)
    {
        $_SESSION[self::getKey('popup')] = $popup;
    }
}
