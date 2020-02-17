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
 * @property-read integer id основной идентификатор пользователя на OAuth-сервере
 * @property-read string login логин, с которым пользователь авторизовался. Может быть телефон, email или ничего
 * @property-read integer[] identifiers все идентификаторы пользователя на OAuth-сервере
 * @property-read Carbon created дата регистрации пользователя
 * @property-read Carbon updated дата последнего изменения профиля пользователя
 * @property-read Carbon entered дата последней авторизации пользователя
 * @property-read Carbon birthday день рождения
 * @property string name полное имя
 * @property string givenName имя
 * @property string parentName отчество
 * @property string familyName фамилия
 * @property string gender пол
 * @property-read string[] email адреса почты пользователя (первый — основной)
 * @property-read string[] phone телефоны пользователя (первый — основной)
 * @property-read array properties дополнительные свойства пользователя
 */
class User extends AnyModel implements ResourceOwnerInterface
{

    const MALE = 'male';
    const FEMALE = 'female';

    protected $strings = ['login', 'name', 'given_name', 'parent_name', 'family_name', 'gender'];
    protected $dates = ['created', 'updated', 'entered', 'birthday'];
    protected $protected = ['id', 'created', 'updated', 'entered', 'email', 'phone'];

    protected function sanitizeData()
    {
        parent::sanitizeData();
        $this->sanitized['id'] = (int)$this->data['id'][0];
        $this->sanitized['identifiers'] = (array)$this->data['id'];

        $properties = [];
        foreach ($this->data['properties'] as $property) {
            switch ($property['type']) {
                case 'string':
                    $properties[$property['key']] = $this->sanitizeString($property['value']);
                    break;
                case 'number':
                    $properties[$property['key']] = $this->sanitizeNumber($property['value']);
                    break;
                case 'date':
                    $properties[$property['key']] = $this->sanitizeDate($property['value']);
                    break;
                case 'boolean':
                    $properties[$property['key']] = $this->sanitizeBoolean($property['value']);
                    break;
                case 'json':
                    $properties[$property['key']] = json_decode($property['value'], true);
                    break;
            }
        }
        $this->sanitized['properties'] = $properties;

    }


    /**
     * @inheritDoc
     */
    public function getId()
    {
        return $this->id;
    }

}
