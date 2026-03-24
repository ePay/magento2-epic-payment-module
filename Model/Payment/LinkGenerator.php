<?php
namespace Epay\Magento2EpicPaymentModule\Model\Payment;

use Epay\Magento2EpicPaymentModule\Model\Payment\EpayHandler;
use Epay\Magento2EpicPaymentModule\Helper\EpayPaymentHelper;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\ScopeInterface;

class LinkGenerator
{
    private UrlInterface $urlBuilder;
    private ScopeConfigInterface $scopeConfig;
    private CategoryFactory $categoryFactory;
    private EpayHandler $epayHandler;
    private EpayPaymentHelper $epayPaymentHelper;

    public function __construct(
        UrlInterface $urlBuilder,
        ScopeConfigInterface $scopeConfig,
        CategoryFactory $categoryFactory,
        EpayHandler $epayHandler,
        EpayPaymentHelper $epayPaymentHelper
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->categoryFactory = $categoryFactory;
        $this->epayHandler = $epayHandler;
        $this->epayPaymentHelper = $epayPaymentHelper;
    }

    public function generateLink(OrderInterface $order): string
    {
        $acceptUrl = $this->urlBuilder->getUrl(
            'epay/payment/accept',
            ['_secure' => true]
        );

        $returnUrl = $this->urlBuilder->getUrl(
            'epay/payment/cancel',
            ['_secure' => true]
        );

        $failureUrl = $this->urlBuilder->getUrl(
            'epay/payment/failure',
            ['_secure' => true]
        );

        $notificationUrl = $this->urlBuilder->getUrl(
            'epay/payment/callback',
            ['_secure' => true]
        );

        $storeId = (int)$order->getStoreId();

        $apikey = $this->scopeConfig->getValue(
            'payment/epayepicpayment/apikey',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $posid = $this->scopeConfig->getValue(
            'payment/epayepicpayment/posid',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $ageVerificationMode = $this->scopeConfig->getValue(
            'payment/epayepicpayment/ageverificationmode',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $instantCapture = $this->scopeConfig->getValue(
            'payment/epayepicpayment/instantcapture',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $enableInvoiceData = $this->scopeConfig->getValue(
            'payment/epayepicpayment/enableinvoicedata',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $customerId = null;
        $ageVerificationMinimumAge = null;
        $ageVerificationCountry = null;

        $countryId = $order->getShippingAddress()?->getCountryId();

        if (
            $ageVerificationMode === 'ageverification_enabled_all'
            || ($ageVerificationMode === 'ageverification_enabled_dk' && $countryId === 'DK')
        ) {
            $minimumuserage = 0;
            $orderItems = $order->getAllVisibleItems();

            if ($orderItems) {
                foreach ($orderItems as $item) {
                    $product_minimumuserage = (int)$item->getProduct()->getData('ageVerification');

                    $category_minimumuserage = 0;
                    $categoryIds = (array)$item->getProduct()->getCategoryIds();

                    foreach ($categoryIds as $categoryId) {
                        $category = $this->categoryFactory->create()->load((int)$categoryId);

                        $category_minimumuserage = max(
                            $category_minimumuserage,
                            (int)$category->getData('ageVerification')
                        );
                    }

                    $minimumuserage = max(
                        (int)$minimumuserage,
                        (int)$product_minimumuserage,
                        (int)$category_minimumuserage
                    );
                }
            }

            if ($minimumuserage > 0) {
                $ageVerificationMinimumAge = $minimumuserage;
                $ageVerificationCountry = $countryId;

                if (!$order->getCustomerIsGuest()) {
                    $customerId = $order->getCustomerId();
                }
            }
        }

        if ($enableInvoiceData) {
            $customerData = $this->epayPaymentHelper->createCustomerData($order);
            $orderLines = $this->epayPaymentHelper->createOrderLines($order);
        }

        $this->epayHandler->setAuthData($apikey, $posid);

        $result = $this->epayHandler->createPaymentRequest(
            $order->getIncrementId(),
            (int)round((float)$order->getGrandTotal() * 100),
            $order->getOrderCurrencyCode(),
            ($instantCapture ? "NO_VOID" : "OFF"),
            $acceptUrl,
            $returnUrl,
            $failureUrl,
            $notificationUrl,
            $ageVerificationMinimumAge,
            $ageVerificationCountry,
            $customerId,
            $customerData,
            $orderLines
        );

        if (!is_object($result) || empty($result->paymentWindowUrl)) {
            $errorMessage = 'Could not create payment link';

            if (is_object($result)) {
                if (!empty($result->message)) {
                    $errorMessage .= ': ' . $result->message;
                }

                if (!empty($result->errorCode)) {
                    $errorMessage .= ' (' . $result->errorCode . ')';
                }

                if (!empty($result->errors) && is_object($result->errors)) {
                    foreach ($result->errors as $field => $messages) {
                        if (is_array($messages)) {
                            foreach ($messages as $msg) {
                                $errorMessage .= "\n" . $field . ': ' . $msg;
                            }
                        }
                    }
                }
            }

            throw new LocalizedException(__($errorMessage));
        }

        return (string)$result->paymentWindowUrl;
    }
}