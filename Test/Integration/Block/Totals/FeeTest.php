<?php declare(strict_types=1);

namespace Buckaroo\HyvaCheckout\Test\Integration\Block\Totals;

use Buckaroo\HyvaCheckout\Block\Totals\Fee;
use Hyva\Checkout\ViewModel\Checkout\PriceSummary\TotalSegments as TotalSegmentsViewModel;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Api\Data\TotalsInterface;
use Magento\Quote\Model\Cart\TotalSegment;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class FeeTest extends TestCase
{
    public function testGetTotalIfSegmentIsNotAnArray()
    {
        $this->expectException(UnexpectedValueException::class);
        $fee = ObjectManager::getInstance()->create(Fee::class, ['data' => []]);
        $fee->setData('segment', 'foobar');
        $fee->getTotal();
    }

    public function testGetTotalIfSegmentIsWorkingForReal()
    {
        $this->prepareQuoteTotals();

        $totalSegmentsViewModel = ObjectManager::getInstance()->get(TotalSegmentsViewModel::class);
        $totals = $totalSegmentsViewModel->getTotals();
        $this->assertInstanceOf(TotalsInterface::class, $totals);

        $segment = $this->extractBuckarooTotalSegment($totals);
        $this->assertInstanceOf(TotalSegment::class, $segment);
        $segment->getExtensionAttributes()->setBuckarooFee(42.0);

        $fee = ObjectManager::getInstance()->create(Fee::class, ['data' => []]);
        $fee->setData('segment', $segment->toArray());
        $this->assertEquals(42.0, $fee->getTotal());
    }

    private function extractBuckarooTotalSegment(TotalsInterface $totals): ?TotalSegment
    {
        foreach ($totals->getTotalSegments() as $segment) {
            if ($segment['code'] === 'buckaroo_fee') {
                return $segment;
            }
        }

        return null;
    }

    private function prepareQuoteTotals()
    {
        $checkoutSession = ObjectManager::getInstance()->create(CheckoutSession::class);
        ObjectManager::getInstance()->addSharedInstance($checkoutSession, CheckoutSession::class);

        $quote = ObjectManager::getInstance()->get(Quote::class);
        $quote->setItems();

        $quoteRepository = ObjectManager::getInstance()->get(QuoteRepository::class);
        $quoteRepository->save($quote);

        $quote->collectTotals();

        $checkoutSession->setQuoteId($quote->getId());
    }
}
