<?php


namespace Codewiser\UAC\Model;


/**
 * Class Subscription
 * @package Codewiser\UAC\Model
 *
 * @property-read string $id Идентификатор подписки
 * @property-read string $title Локализованное название подписки
 * @property-read string $description Описание подписки
 * @property-read string|null $last_link Адрес с содержимым последней рассылки
 * @property-read boolean $subscribed Подписан ли пользователь
 */
class Subscription extends AnyModel
{

}