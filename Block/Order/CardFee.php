<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Block\Order;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;

class CardFee extends Template
{
    public function initTotals(): self
    {
        $totalsBlock = $this->getParentBlock();
        if (!$totalsBlock || !method_exists($totalsBlock, 'getOrder')) {
            return $this;
        }

        $order = $totalsBlock->getOrder();
        $payment = $order ? $order->getPayment() : null;
        $feeAmount = $payment ? (float)$payment->getAdditionalInformation('epay_card_fee_amount') : 0.0;

        if ($feeAmount <= 0.0) {
            return $this;
        }

        $total = new DataObject([
            'code' => 'epay_card_fee',
            'value' => $feeAmount,
            'label' => __('Payment fee'),
        ]);

        if ($order->isCurrencyDifferent()) {
            $rate = (float)$order->getBaseToOrderRate();
            if ($rate > 0.0) {
                $total->setBaseValue(round($feeAmount / $rate, 4));
            }
        }

        $totalsBlock->addTotal($total, 'grand_total');

        return $this;
    }
}
