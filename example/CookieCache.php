<?php

class CookieCache implements \Codewiser\UAC\Contracts\CacheContract
{

    public function get($key, $default = null)
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException("Cache key must be a string value");
        }

        return isset($_COOKIE[$key]) ? unserialize($_COOKIE[$key]): $default;
    }

    public function set($key, $value, $ttl = null)
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException("Cache key must be a string value");
        }

        if ($ttl instanceof DateInterval) {
            $ttl = $ttl->s;
        }

        setcookie($key, serialize($value), $ttl ? time() + $ttl : 0);
    }

    public function delete($key)
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException("Cache key must be a string value");
        }

        setcookie($key, null);
    }

    public function has($key)
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException("Cache key must be a string value");
        }

        return isset($_COOKIE[$key]);
    }
}
