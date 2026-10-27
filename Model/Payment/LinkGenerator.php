<?php
namespace Epay\Magento2EpicPaymentModule\Model\Payment;

use Epay\Magento2EpicPaymentModule\Model\Payment\EpayHandler;
use Epay\Magento2EpicPaymentModule\Helper\EpayPaymentHelper;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\ScopeInterface;

class LinkGenerator
{
    private const ALLOWED_PAYMENT_METHODS = [
        'epayepicpayment' => 'CARD',
        'epayepicpayment_anyday' => 'ANYDAY',
        'epayepicpayment_applepay' => 'APPLE_PAY',
        'epayepicpayment_googlepay' => 'GOOGLE_PAY',
        'epayepicpayment_klarna' => 'KLARNA',
        'epayepicpayment_mobilepay' => 'VIPPS_MOBILEPAY',
        'epayepicpayment_swish' => 'SWISH',
        'epayepicpayment_viabill' => 'VIABILL',
    ];

    private StoreManagerInterface $storeManager;
    private ScopeConfigInterface $scopeConfig;
    private CategoryFactory $categoryFactory;
    private EpayHandler $epayHandler;
    private EpayPaymentHelper $epayPaymentHelper;

    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        CategoryFactory $categoryFactory,
        EpayHandler $epayHandler,
        EpayPaymentHelper $epayPaymentHelper
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->categoryFactory = $categoryFactory;
        $this->epayHandler = $epayHandler;
        $this->epayPaymentHelper = $epayPaymentHelper;
    }

    public function supportsPaymentMethod(string $paymentMethodCode): bool
    {
        return isset(self::ALLOWED_PAYMENT_METHODS[$paymentMethodCode]);
    }

    public function generateLink(OrderInterface $order): string
    {
        return $this->generatePaymentUrl($order, false);
    }

    public function generatePaymentLink(OrderInterface $order): string
    {
        return $this->generatePaymentUrl($order, true);
    }

    private function generatePaymentUrl(OrderInterface $order, bool $isPaymentLink): string
    {
        $paymentMethodCode = (string)$order->getPayment()->getMethod();
        $allowedPaymentMethods = isset(self::ALLOWED_PAYMENT_METHODS[$paymentMethodCode])
            ? [self::ALLOWED_PAYMENT_METHODS[$paymentMethodCode]]
            : [];
        $storeId = (int)$order->getStoreId();
        $store = $this->storeManager->getStore($storeId);
        $baseUrl = rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_LINK, true), '/');

        $acceptUrl = $baseUrl . '/epay/payment/accept/';
        $returnUrl = $baseUrl . '/epay/payment/cancel/';
        $failureUrl = $baseUrl . '/epay/payment/failure/';
        $notificationUrl = $baseUrl . '/epay/payment/callback/';
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
        $customerData = null;
        $orderLines = null;

        $shippingAddress = $order->getShippingAddress();
        $countryId = $shippingAddress ? $shippingAddress->getCountryId() : null;

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

        $result = $this->epayHandler->{$isPaymentLink ? 'createPaymentLink' : 'createPaymentRequest'}(
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
            $orderLines,
            $allowedPaymentMethods
        );

        $urlProperty = $isPaymentLink ? 'url' : 'paymentWindowUrl';
        if (!is_object($result) || empty($result->{$urlProperty})) {
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

        return (string)$result->{$urlProperty};
    }
}
