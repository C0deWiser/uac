<?php

namespace Codewiser\UAC\Mock;

use Codewiser\UAC\Contracts\CacheContract;

class MockCache implements CacheContract
{

    protected $data = [];
    protected $ttl = [];

    protected function clearOutdated()
    {
        foreach ($this->ttl as $key => $ttl) {
            if ($ttl < time()) {
                if (isset($this->data[$key])) {
                    unset($this->data[$key]);
                }
                unset($this->ttl[$key]);
            }
        }
    }

    public function get($key, $default = null)
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException("Context key must be a string value");
        }

        $this->clearOutdated();

        return isset($this->data[$key]) ? unserialize($this->data[$key]) : $default;
    }

    public function set($key, $value, $ttl = null)
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException("Context key must be a string value");
        }

        $this->data[$key] = serialize($value);

        if ($ttl) {
            if ($ttl instanceof \DateInterval) {
                $ttl = $ttl->s;
            }

            $this->ttl[$key] = time() + $ttl;
        }

        return true;
    }

    public function delete($key)
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException("Context key must be a string value");
        }

        if (isset($this->data[$key])) {
            unset($this->data[$key]);
        }

        if (isset($this->ttl[$key])) {
            unset($this->ttl[$key]);
        }

        return true;
    }

    public function has($key)
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException("Context key must be a string value");
        }

        $this->clearOutdated();

        return isset($this->data[$key]);
    }
}
