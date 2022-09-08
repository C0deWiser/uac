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
 * @property string|null $access_token
 */
class ContextManager
{
    /**
     * Время жизни контекста. После этого он считается недействительным.
     *
     * @var int|DateInterval
     */
    protected $ttl = 3600;
    protected $stateValue;
    protected $contextData = array();

    /**
     * Эти ключи сохраняем в контексте (то есть с привязкой к state), остальные — просто так.
     *
     * @var array
     */
    protected $contextKeys = array('response_type', 'return_path', 'run_in_popup', 'locale');

    /**
     * @var CacheContract
     */
    protected $context;

    /**
     * @param CacheContract $context
     */
    public function __construct($context)
    {
        $this->context = $context;
    }

    protected function sessionKey($state)
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
            $this->context->set($this->sessionKey($this->stateValue), $this->contextData, $this->ttl);
        }
    }

    /**
     * Контекст сохраняется в сессии с привязкой к значению state. Если такой есть в сессии, то он будет загружен в класс.
     * Контекст может быть загружен только один раз, после этого он уничтожается.
     *
     * @param string $state
     * @return boolean
     */
    protected function loadIfPossible($state)
    {
        $key = $this->sessionKey($state);

        $this->stateValue = null;
        $this->contextData = [];

        if ($this->context->has($key)) {
            $contextData = $this->context->get($key);
            $this->context->delete($key);
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
    public function restoreContext($state)
    {
        return $this->loadIfPossible($state);
    }

    public function __get($name)
    {
        if ($name == 'state') {
            return $this->stateValue;
        } elseif (in_array($name, $this->contextKeys)) {
            return isset($this->contextData[$name]) ? $this->contextData[$name] : null;
        } else {
            return $this->context->get($name);
        }
    }
    public function __set($name, $value)
    {
        if ($name == 'state') {
            $this->stateValue = $value;
            $this->saveIfPossible();
        } elseif (in_array($name, $this->contextKeys)) {
            $this->contextData[$name] = $value;
            $this->saveIfPossible();
        } else {
            $this->context->set($name, $value);
        }
    }
    public function __isset($name)
    {
        if ($name == 'state') {
            return (boolean)$this->stateValue;
        } elseif (in_array($name, $this->contextKeys)) {
            return isset($this->contextData[$name]) && $this->contextData[$name];
        } else {
            return $this->context->has($name);
        }
    }
    public function __unset($name)
    {
        if ($name == 'state') {
            if ($this->stateValue) {
                $this->context->delete($this->sessionKey($this->stateValue));
                $this->stateValue = null;
            }
        } elseif (in_array($name, $this->contextKeys)) {
            if (isset($this->contextData[$name])) {
                unset($this->contextData[$name]);
            }
            $this->saveIfPossible();
        } else {
            $this->context->delete($name);
        }
    }

    /**
     * Clear context
     */
    public function clearContext()
    {
        if ($this->stateValue) {
            $this->context->delete($this->sessionKey($this->stateValue));
            $this->stateValue = null;
        }
        $this->contextData = array();
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        if ($this->stateValue) {
            return array_merge(array('state' => $this->stateValue), $this->contextData);
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
