<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Model\Payment;

use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Magento\Quote\Model\Quote;

/**
 * Keep Hyvä's order placement and send the customer to the existing ePay flow.
 */
class HyvaCheckoutPlaceOrderService extends AbstractPlaceOrderService
{
    public function canRedirect(): bool
    {
        return true;
    }

    public function getRedirectUrl(Quote $quote, ?int $orderId = null): string
    {
        return 'epay/payment/redirect';
    }
}
