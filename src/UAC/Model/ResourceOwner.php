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
 * @property-read null|Carbon $authorized_at Дата последней авторизации пользователя
 * @property-read null|boolean $has_zenit_pass Профиль полностью заполнен (т.н. «паспорт болельщика»)
 *
 * @property-read null|Carbon $birthday День рождения
 * @property-read null|string $name Полное имя
 * @property-read null|string $first_name Имя
 * @property-read null|string $parent_name Отчество
 * @property-read null|string $family_name Фамилия
 * @property-read null|string $picture Фотография
 *
 * @property-read null|string $preferred_locale Предпочитаемый язык пользователя
 * @property-read boolean $terms_accepted Согласие с правилами предоставления услуг
 * @property-read null|string $card Номер карты лояльности
 *
 * @property-read null|string $email Адрес почты пользователя
 * @property-read null|string $phone Телефон пользователя
 *
 * @property-read array<DiscountCard> $cards Информация о скидочных картах.
 * @property-read array<Address> $addresses Адреса пользователя
 * @property-read array<Car> $cars Автомобили пользователя
 * @property-read Notifications $notifications Разрешения на уведомления
 * @property-read array<Subscription> $subscriptions Подписки пользователя
 */
class ResourceOwner extends AnyModel implements ResourceOwnerInterface
{
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'authorized_at' => 'datetime',
        'birthday' => 'datetime:Y-m-d',

        'cards' => [DiscountCard::class],
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
