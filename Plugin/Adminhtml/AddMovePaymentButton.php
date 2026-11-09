<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Plugin\Adminhtml;

use Epay\Magento2EpicPaymentModule\Model\Payment\LinkGenerator;
use Epay\Magento2EpicPaymentModule\Model\Payment\PaymentRegistrationChecker;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Sales\Block\Adminhtml\Order\View;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;

class AddMovePaymentButton
{
    private ScopeConfigInterface $scopeConfig;
    private AuthorizationInterface $authorization;
    private UrlInterface $urlBuilder;
    private LinkGenerator $linkGenerator;
    private PaymentRegistrationChecker $paymentRegistrationChecker;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        AuthorizationInterface $authorization,
        UrlInterface $urlBuilder,
        LinkGenerator $linkGenerator,
        PaymentRegistrationChecker $paymentRegistrationChecker
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->authorization = $authorization;
        $this->urlBuilder = $urlBuilder;
        $this->linkGenerator = $linkGenerator;
        $this->paymentRegistrationChecker = $paymentRegistrationChecker;
    }

    public function afterSetLayout(View $subject, View $result): View
    {
        $order = $subject->getOrder();
        if (!$order || !$this->canMovePayment($order)) {
            return $result;
        }

        $url = $this->urlBuilder->getUrl('epay/order/movePayment', [
            'order_id' => (int)$order->getEntityId(),
            'parent_order_id' => (int)$order->getRelationParentId(),
        ]);

        $subject->addButton('epay_move_payment', [
            'label' => __('Move ePay payment from original order'),
            'class' => 'primary',
            'onclick' => 'setLocation(' . json_encode($url) . ')',
        ]);

        return $result;
    }

    private function canMovePayment(Order $order): bool
    {
        $payment = $order->getPayment();

        return $payment
            && $order->getRelationParentId()
            && !$order->isCanceled()
            && $this->linkGenerator->supportsPaymentMethod((string)$payment->getMethod())
            && $this->scopeConfig->isSetFlag(
                'payment/epayepicpayment/keep_payment_onedit',
                ScopeInterface::SCOPE_STORE,
                (int)$order->getStoreId()
            )
            && $this->authorization->isAllowed('Epay_Magento2EpicPaymentModule::move_payment')
            && !$this->paymentRegistrationChecker->isPaymentRegistered($order);
    }
}
