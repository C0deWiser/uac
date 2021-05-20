<?php
/**
 * Created by PhpStorm.
 * User: pm
 * Date: 2019-09-26
 * Time: 19:15
 */

namespace Codewiser\UAC\Model;

use Carbon\Carbon;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;

/**
 * Class ResourceOwner
 *
 * @package UAC
 * @property-read integer $id Идентификатор пользователя на OAuth-сервере
 *
 * @property-read Carbon $created_at Дата регистрации пользователя
 * @property-read Carbon $updated_at Дата последнего изменения профиля пользователя
 * @property-read Carbon $authorized_at Дата последней авторизации пользователя
 *
 * @property-read Carbon $birthday День рождения
 * @property-read string $name Полное имя
 * @property-read string $first_name Имя
 * @property-read string $parent_name Отчество
 * @property-read string $family_name Фамилия
 *
 * @property-read string $preferred_locale Предпочитаемый язык пользователя
 *
 * @property-read string $email Адрес почты пользователя
 * @property-read string $phone Телефон пользователя
 *
 * @property-read array|Address[] $addresses Адреса пользователя
 * @property-read array|Car[] $cars Автомобили пользователя
 * @property-read Notifications $notifications Разрешения на уведомления
 * @property-read array|Subscription[] $subscriptions Подписки пользователя
 *
 */
class ResourceOwner extends AnyModel implements ResourceOwnerInterface
{
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'authorized_at' => 'datetime',
        'birthday' => 'datetime:Y-m-d',

        'addresses' => [Address::class],
        'cars' => [Car::class],
        'subscriptions' => [Subscription::class],

        'notifications' => Notifications::class
    ];

    public function getId()
    {
        return $this->id;
    }

}
