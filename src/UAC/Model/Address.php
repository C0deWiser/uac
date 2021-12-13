<?php


namespace Codewiser\UAC\Model;


/**
 * Class Address
 * @package Codewiser\UAC\Model
 *
 * @property string $label Название адреса
 * @property string|null $country Страна
 * @property string|null $region Регион
 * @property string|null $city Город
 * @property string|null $street Улица
 * @property string|null $building Здание
 * @property string|null $block Корпус
 * @property string|null $apartment Помещение
 * @property integer|null $zip Почтовый индекс
 */
class Address extends AnyModel
{

}