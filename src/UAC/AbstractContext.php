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
abstract class AbstractContext
{
    /**
     * Время жизни контекста. После этого он считается недействительным.
     * @var string
     */
    protected $ttl = '3600';
    protected $stateValue;
    protected $contextData = array();

    abstract protected function sessionSet($name, $value);
    abstract protected function sessionGet($name);
    abstract protected function sessionHas($name);
    abstract protected function sessionDel($name);

    protected function contextKey($state)
    {
        return "oauth2:state:{$state}";
    }

    /**
     * Конекст сохраняется в сессии с привязкой к значению state. Если он есть, то контекст можно сохранить/обновить.
     */
    protected function saveIfPossible()
    {
        if ($this->stateValue) {
            $this->contextData['issued_at'] = time();
            $this->sessionSet($this->contextKey($this->stateValue), serialize($this->contextData));
        }
    }

    /**
     * Конекст сохраняется в сессии с привязкой к значению state. Если такой есть в сессии, то он будет загружен в класс.
     * Контекст может быть загружен только один раз, после этого он уничтожается.
     * @param $state
     * @return boolean
     */
    protected function loadIfPossible($state)
    {
        $key = $this->contextKey($state);

        if ($this->sessionHas($key)) {
            $contextData = unserialize($this->sessionGet($key));
            $this->sessionDel($key);
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
     * @param $state
     * @return bool
     */
    public function restoreContext($state)
    {
        return $this->loadIfPossible($state);
    }

    public function __get($name)
    {
        switch ($name) {
            case 'access_token':
                return $this->sessionGet($name);
            case 'state':
                return $this->stateValue;
            default:
                return isset($this->contextData[$name]) ? $this->contextData[$name] : null;
        }
    }
    public function __set($name, $value)
    {
        switch ($name) {
            case 'access_token':
                $this->sessionSet($name, $value);
                break;
            case 'state':
                $this->stateValue = $value;
                $this->saveIfPossible();
                break;
            default:
                $this->contextData[$name] = $value;
                $this->saveIfPossible();
                break;
        }
    }
    public function __isset($name)
    {
        switch ($name) {
            case 'access_token':
                return $this->sessionHas($name);
            case 'state':
                return (boolean)$this->stateValue;
            default:
                return (isset($this->contextData[$name]) && $this->contextData[$name]) ? true : false;
        }
    }
    public function __unset($name)
    {
        switch ($name) {
            case 'access_token':
                $this->sessionDel($name);
                break;
            case 'state':
                if ($this->stateValue) {
                    $this->sessionDel($this->contextKey($this->stateValue));
                    $this->stateValue = null;
                }
                break;
            default:
                if (isset($this->contextData[$name])) {
                    unset($this->contextData[$name]);
                }
                $this->saveIfPossible();
                break;
        }
    }

    /**
     * Clear context
     */
    public function clearContext()
    {
        if ($this->stateValue) {
            $this->sessionDel($this->contextKey($this->stateValue));
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
}
