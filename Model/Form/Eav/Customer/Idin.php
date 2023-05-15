<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Model\Form\Eav\Customer;

use Hyva\Checkout\Model\Form\EntityField\EavAttributeField;

class Idin extends EavAttributeField
{
    public function canRender(): bool
    {
        return false;
    }

    public function isRequired(): bool
    {
        return false;
    }
}
