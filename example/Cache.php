<?php

class Cache implements \Codewiser\UAC\Contracts\CacheContract
{

    public function get($key, $default = null)
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException("Cache key must be a string value");
        }

        return isset($_SESSION[$key]) ? unserialize($_SESSION[$key]) : $default;
    }

    public function set($key, $value, $ttl = null)
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException("Cache key must be a string value");
        }

        $_SESSION[$key] = serialize($value);
    }

    public function delete($key)
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException("Cache key must be a string value");
        }

        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    public function has($key)
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException("Cache key must be a string value");
        }

        return isset($_SESSION[$key]);
    }
}
