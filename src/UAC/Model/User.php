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
 * @property-read integer id
 * @property-read string login
 * @property-read integer[] identifiers
 * @property-read Carbon created
 * @property-read Carbon updated
 * @property-read Carbon entered
 * @property-read Carbon birthday
 * @property string name
 * @property string givenName
 * @property string parentName
 * @property string familyName
 * @property string gender
 * @property-read string[] email
 * @property-read string[] phone
 * @property-read array properties
 */
class User extends AnyModel implements ResourceOwnerInterface
{

    const MALE = 'male';
    const FEMALE = 'female';

    protected $strings = ['login', 'name', 'given_name', 'parent_name', 'family_name', 'gender', 'phone'];
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
