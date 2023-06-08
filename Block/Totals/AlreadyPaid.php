<?php

declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Block\Totals;

class AlreadyPaid extends \Magento\Framework\View\Element\Template
{
    /**
     * Get totals from array of data
     *
     * @return array
     */
    public function getTotals(): array
    {
        $totalData = $this->getSegment();
        if (
            isset($totalData['title']) &&
            is_scalar($totalData['title'])
        ) {
            $data = json_decode($totalData['title'], true);
            if(is_array($data)) {
                return array_filter($data, function($row) {
                    return isset($row['label']) && isset($row['amount']);
                });
            }
        }
        return [];
    }
}
