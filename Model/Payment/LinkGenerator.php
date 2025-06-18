<?php
namespace Epay\Magento2EpicPaymentModule\Model\Payment;

use Magento\Sales\Api\Data\OrderInterface;
use Epay\Magento2EpicPaymentModule\Model\Payment\EpayHandler;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class LinkGenerator
{
    private $urlBuilder;
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        UrlInterface $urlBuilder,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->urlBuilder = $urlBuilder;
        $this->scopeConfig  = $scopeConfig;
    } 

    public function generateLink(OrderInterface $order): string
    {
        $acceptUrl = $this->urlBuilder->getUrl(
            'epay/payment/accept',
            ['_secure' => true]
        );
        
        $failureUrl = $this->urlBuilder->getUrl(
            'epay/payment/cancel',
            ['_secure' => true]
        );

        $notificationUrl = $this->urlBuilder->getUrl(
            'epay/payment/callback',
            ['_secure' => true]
        );

        $apikey = $this->scopeConfig->getValue(
            'payment/epayepicpayment/apikey',
            ScopeInterface::SCOPE_STORE
        );
        
        $posid = $this->scopeConfig->getValue(
            'payment/epayepicpayment/posid',
            ScopeInterface::SCOPE_STORE
        );

        $epayHandler = new EpayHandler;
        $epayHandler->setAuthData($apikey, $posid);

        $result = $epayHandler->createPaymentRequest($order->getIncrementId(), (int)round($order->getGrandTotal() * 100), $order->getOrderCurrencyCode(), "OFF", $acceptUrl, $failureUrl, $notificationUrl);

        return $result->paymentWindowUrl;
    }
}
