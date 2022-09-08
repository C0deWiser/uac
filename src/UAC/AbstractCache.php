<?php


namespace Codewiser\UAC;


/**
 * Абстрактный кеш.
 *
 * При его реализации вы должны взять на себя труд по сереализации/десереализации значения.
 *
 * Обязательно добавляйте прфикс к используемым ключам.
 *
 * @package Codewiser\UAC
 *
 * @deprecated
 */
abstract class AbstractCache
{
    /**
     * Положить переменную в кеш.
     *
     * @param string $key
     * @param mixed $value
     * @param integer $timeout Задается в секундах.
     * @return void
     */
    abstract public function put($key, $value, $timeout);

    /**
     * Проверить наличие переменной в кеше.
     *
     * @param string $key
     * @return boolean
     */
    abstract public function has($key);

    /**
     * Достать переменную из кеша.
     *
     * @param string $key
     * @return mixed
     */
    abstract public function get($key);

    /**
     * Удалить переменную из кеша.
     *
     * @param string $key
     * @return void
     */
    abstract public function forget($key);
}
