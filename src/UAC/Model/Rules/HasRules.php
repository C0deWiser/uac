<?php


namespace Codewiser\UAC\Model\Rules;

use Codewiser\UAC\Model\Rules\Rules;

trait HasRules
{
    /**
     * @var Rules
     */
    protected $rules = [];

    /**
     * Get rules for given attribute.
     *
     * @return Rules
     */
    public function rules()
    {
        return $this->rules;
    }

}