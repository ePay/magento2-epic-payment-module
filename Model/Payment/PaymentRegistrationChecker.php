<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Model\Payment;

use Magento\Sales\Api\Data\OrderInterface;

class PaymentRegistrationChecker
{
    public function isPaymentRegistered(OrderInterface $order): bool
    {
        $payment = $order->getPayment();
        $transactionId = trim((string)$payment->getLastTransId());
        $registeredTransactionId = trim((string)$payment->getAdditionalInformation('epay_payment_id'));

        return $transactionId !== ''
            && $registeredTransactionId !== ''
            && hash_equals($transactionId, $registeredTransactionId);
    }
}
