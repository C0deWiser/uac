<?php

namespace Codewiser\UAC\Model;

use Carbon\Carbon;

abstract class AnyModel
{
    protected $data;
    protected $sanitized;

    protected $strings = [];
    protected $dates = [];
    protected $booleans = [];
    protected $emails = [];

    protected $protected = [];
    /**
     * [property -> class]
     * @var array
     */

    public function __construct($data)
    {
        $this->data = $data;

        $this->sanitizeData();
    }

    /**
     * @return mixed
     */
    public function getSanitized()
    {
        return $this->sanitized;
    }

    protected function sanitizeData()
    {
        foreach ($this->data as $key => $value) {
            if (in_array($key, $this->dates)) {
                $this->sanitized[$key] = $this->sanitizeDate($value);
            } elseif (in_array($key, $this->strings)) {
                $this->sanitized[$key] = $this->sanitizeString($value);
            } elseif (in_array($key, $this->booleans)) {
                $this->sanitized[$key] = $this->sanitizeBoolean($value);
            } elseif (in_array($key, $this->emails)) {
                $this->sanitized[$key] = $this->sanitizeEmail($value);
            } else {
                $this->sanitized[$key] = $value;
            }
        }
    }

    protected function sanitizeNumber($value)
    {
        return $value * 1.0;
    }
    protected function sanitizeEmail($value)
    {
        return filter_var($value, FILTER_SANITIZE_EMAIL);
    }
    protected function sanitizeBoolean($value)
    {
        return !!$value;
    }
    protected function sanitizeString($value)
    {
        return filter_var($value, FILTER_SANITIZE_STRING);
    }
    protected function sanitizeDate($value)
    {
        return $value ? Carbon::parse($value) : null;
    }

    public function toArray()
    {
        $data = [];
        foreach ($this->sanitized as $key => $value) {
            $data[$key] = $this->primitive($value);
        }
        return $data;
    }
    protected function primitive($value)
    {
        if ($value instanceof AnyModel) {
            return $value->toArray();
        }
        if ($value instanceof Carbon) {
            return $value->format('c');
        }
        return $value;
    }

    public function __get($name)
    {
        $key = $this->snake($name);
        if (isset($this->sanitized[$key])) {
            return $this->sanitized[$key];
        }
    }

    private function snake($input) {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }


}
