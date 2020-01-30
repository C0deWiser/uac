<?php


class Context extends \Codewiser\UAC\AbstractContext
{
    protected function sessionSet($name, $value)
    {
        $_SESSION[$name] = $value;
    }

    protected function sessionGet($name)
    {
        return $_SESSION[$name];
    }

    protected function sessionHas($name)
    {
        return isset($_SESSION[$name]);
    }

    protected function sessionDel($name)
    {
        unset($_SESSION[$name]);
    }
}