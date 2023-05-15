<?php

namespace Buckaroo\HyvaCheckout\Model\Validation\Rules;

use Rakit\Validation\Rule;

class NlBeDePhone extends Rule
{

    /** @var string */
    protected $message = "Phone number should be correct.";
    
    /** @var array */
    protected $fillableParams = ['country'];

    /**
     * Check the $value is valid
     *
     * @param mixed $value
     * @return bool
     */
    public function check($value): bool
    {
        $this->requireParameters(['country']);
        $country =  $this->parameter('country');

        $countryLimits = [
            'NL' => [
                "min" => 10,
                "max" => 12
            ],
            'BE' => [
                "min" => 9,
                "max" => 12
            ],
            'DE' => [
                "min" => 11,
                "max" => 14
            ]
        ];

        if(!is_numeric($value)) {
            return false;
        }

        if(!isset($countryLimits[$country])) {
            return true;
        }

        $limits = $countryLimits[$country];
        $valueLength = strlen((string)$value);

        return $valueLength >= $limits['min'] && $valueLength <= $limits['max'];
    }
}
