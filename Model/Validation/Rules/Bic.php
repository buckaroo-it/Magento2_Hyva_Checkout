<?php

namespace Buckaroo\HyvaCheckout\Model\Validation\Rules;

use Rakit\Validation\Rule;

class Bic extends Rule
{

    /** @var string */
    protected $message = "Enter Valid BIC number";
    

    /**
     * Check the $value is valid
     *
     * @param mixed $value
     * @return bool
     */
    public function check($value): bool
    {
       $match = preg_match('/^([a-zA-Z]){4}([a-zA-Z]){2}([0-9a-zA-Z]){2}([0-9a-zA-Z]{3})?$/', $value);
       return $match !== false && $match > 0;
    }
}
