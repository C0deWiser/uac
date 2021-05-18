<?php


namespace Codewiser\UAC\Model\Rules;


class Rules
{
    protected $rules;

    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }

    /**
     * Get all rules for the given attribute.
     *
     * @param string $attribute
     * @return array
     */
    public function of($attribute)
    {
        return (array)explode('|', @$this->rules[$attribute]);
    }

    /**
     * Check if user control is required.
     *
     * @param string $attribute
     * @return bool
     */
    public function isRequired($attribute)
    {
        return in_array('required', $this->of($attribute));
    }

    /**
     * Check if user control is readonly.
     *
     * @param string $attribute
     * @return bool
     */
    public function isReadonly($attribute)
    {
        return in_array('readonly', $this->of($attribute));
    }

    /**
     * Get minimum of values.
     *
     * @param string $attribute
     * @return integer|null
     */
    public function min($attribute)
    {
        foreach ($this->of($attribute) as $i) {
            if (strpos($i, "min:") === 0) {
                return substr($i, strlen("min:"));
            }
        }

        return null;
    }

    public function toArray()
    {
        return $this->rules;
    }
}