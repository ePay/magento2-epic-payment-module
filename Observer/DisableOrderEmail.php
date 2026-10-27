<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class DisableOrderEmail implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order) {
            return;
        }

        $payment = $order->getPayment();
        if ($payment && $payment->getMethod() === 'epayepicpayment') {
            $order->setCanSendNewEmailFlag(false);
            $order->setSendEmail(false);
        }
    }
}
