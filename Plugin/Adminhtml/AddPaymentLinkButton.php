<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Plugin\Adminhtml;

use Epay\Magento2EpicPaymentModule\Model\Payment\LinkGenerator;
use Epay\Magento2EpicPaymentModule\Model\Payment\PaymentRegistrationChecker;
use Magento\Backend\Model\UrlInterface;
use Magento\Sales\Block\Adminhtml\Order\View;
use Magento\Sales\Model\Order;

class AddPaymentLinkButton
{
    private LinkGenerator $linkGenerator;
    private PaymentRegistrationChecker $paymentRegistrationChecker;
    private UrlInterface $urlBuilder;

    public function __construct(
        LinkGenerator $linkGenerator,
        PaymentRegistrationChecker $paymentRegistrationChecker,
        UrlInterface $urlBuilder
    )
    {
        $this->linkGenerator = $linkGenerator;
        $this->paymentRegistrationChecker = $paymentRegistrationChecker;
        $this->urlBuilder = $urlBuilder;
    }

    public function afterSetLayout(View $subject, View $result): View
    {
        $order = $subject->getOrder();
        if (!$order || !$this->canCreatePaymentLink($order)) {
            return $result;
        }

        $url = $this->urlBuilder->getUrl('epay/order/createPaymentLink', [
            'order_id' => (int)$order->getEntityId(),
        ]);

        $subject->addButton('epay_create_payment_link', [
            'label' => __('Create ePay payment link'),
            'class' => 'primary',
            'onclick' => 'setLocation(' . json_encode($url) . ')',
        ]);

        return $result;
    }

    private function canCreatePaymentLink(Order $order): bool
    {
        $payment = $order->getPayment();
        if (!$payment || !$this->linkGenerator->supportsPaymentMethod((string)$payment->getMethod())) {
            return false;
        }

        if ($this->paymentRegistrationChecker->isPaymentRegistered($order)) {
            return false;
        }

        return !in_array($order->getState(), [
            Order::STATE_CANCELED,
            Order::STATE_CLOSED,
            Order::STATE_COMPLETE,
        ], true);
    }
}
