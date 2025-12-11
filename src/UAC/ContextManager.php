<?php

namespace Codewiser\UAC;

use Codewiser\UAC\Contracts\CacheContract;
use DateInterval;

/**
 * Class ContextManager
 * @package Codewiser\UAC
 *
 * Access to ContextContract
 *
 * @property string|null $state
 * @property string|null $locale
 * @property string|null $response_type
 * @property string|null $return_path
 * @property boolean|null $run_in_popup
 * @property string|null $oauth2pkceCode
 */
class ContextManager
{
    /**
     * Время жизни контекста. После этого он считается недействительным.
     *
     * @var int|DateInterval
     */
    protected $ttl = 3600;
    protected ?string $stateValue = null;
    protected array $contextData = array();

    /**
     * Эти ключи сохраняем в контексте (то есть с привязкой к state), остальные — просто так.
     *
     * @var array
     */
    protected array $contextKeys = array('response_type', 'return_path', 'run_in_popup', 'locale', 'oauth2pkceCode');

    protected CacheContract $cache;

    public function __construct(CacheContract $context)
    {
        $this->cache = $context;
    }

    protected function sessionKey(string $state): string
    {
        return "oauth2:state:{$state}";
    }

    /**
     * Контекст сохраняется в сессии с привязкой к значению state. Если он есть, то контекст можно сохранить/обновить.
     */
    protected function saveIfPossible()
    {
        if ($this->stateValue) {
            $this->contextData['issued_at'] = time();
            $this->cache->set($this->sessionKey($this->stateValue), $this->contextData, $this->ttl);
        }
    }

    /**
     * Контекст сохраняется в сессии с привязкой к значению state. Если такой есть в сессии, то он будет загружен в класс.
     * Контекст может быть загружен только один раз, после этого он уничтожается.
     *
     * @param string $state
     * @return boolean
     */
    protected function loadIfPossible(string $state): bool
    {
        $key = $this->sessionKey($state);

        $this->stateValue = null;
        $this->contextData = [];

        if ($this->cache->has($key)) {
            $contextData = $this->cache->get($key);
            if ($contextData && ($contextData['issued_at'] + (int)$this->ttl) > time()) {
                $this->stateValue = $state;
                $this->contextData = $contextData;
                return true;
            }
        }
        return false;
    }

    /**
     * Проверяет наличие сохраненного state, одновременно восстанавливая его контекст.
     *
     * @param string $state
     * @return bool
     */
    public function restoreContext(string $state): bool
    {
        return $this->loadIfPossible($state);
    }

    public function __get($name)
    {
        if ($name == 'state') {
            return $this->stateValue;
        } elseif (in_array($name, $this->contextKeys)) {
            return $this->contextData[$name] ?? null;
        }

        return null;
    }
    public function __set($name, $value)
    {
        if ($name == 'state') {
            $this->stateValue = $value;
            $this->saveIfPossible();
        } elseif (in_array($name, $this->contextKeys)) {
            $this->contextData[$name] = $value;
            $this->saveIfPossible();
        }
    }
    public function __isset($name)
    {
        if ($name == 'state') {
            return (boolean)$this->stateValue;
        } elseif (in_array($name, $this->contextKeys)) {
            return isset($this->contextData[$name]) && $this->contextData[$name];
        }

        return false;
    }
    public function __unset($name)
    {
        if ($name == 'state') {
            if ($this->stateValue) {
                $this->cache->delete($this->sessionKey($this->stateValue));
                $this->stateValue = null;
            }
        } elseif (in_array($name, $this->contextKeys)) {
            if (isset($this->contextData[$name])) {
                unset($this->contextData[$name]);
            }
            $this->saveIfPossible();
        }
    }

    /**
     * Clear context
     */
    public function clearContext()
    {
        if ($this->stateValue) {
            $this->cache->delete($this->sessionKey($this->stateValue));
            $this->stateValue = null;
        }
        $this->contextData = array();
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        if ($this->stateValue) {
            return ['state' => $this->stateValue] + $this->contextData;
        } else {
            return $this->contextData;
        }
    }

    /**
     * @param DateInterval|int $ttl
     */
    public function setTtl($ttl): void
    {
        $this->ttl = $ttl;
    }
}
