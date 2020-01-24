<?php

namespace Codewiser\UAC;

/**
 * Class ContextManager
 * @package Codewiser\UAC
 *
 * Store some data in session
 *
 * @property $state
 * @property $response_type
 * @property $return_path
 * @property $run_in_popup
 * @property $access_token
 */
class ContextManager
{
    const KEYS = [
        'state',
        'response_type',
        'return_path',
        'run_in_popup'
    ];
    public function __get($name)
    {
        return $_SESSION['oauth2'.$name];
    }
    public function __set($name, $value)
    {
        $_SESSION['oauth2'.$name] = $value;
    }
    public function __isset($name)
    {
        return isset($_SESSION['oauth2'.$name]);
    }
    public function __unset($name)
    {
        unset($_SESSION['oauth2'.$name]);
    }

    /**
     * Clear context
     */
    public function clear()
    {
        foreach (self::KEYS as $KEY) {
            if (isset($this->$KEY)) {
                unset($this->$KEY);
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
            if (isset($this->$KEY)) {
                $a[$KEY] = $this->$KEY;
            }
        }

        return $a;
    }
}
