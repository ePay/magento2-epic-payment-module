<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Model;

use Epay\Magento2EpicPaymentModule\Model\Payment\EpayHandler;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger as MethodLogger;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractPaymentMethod extends AbstractMethod
{
    protected const SHARED_CONFIG_CODE = 'epayepicpayment';

    protected $_infoBlockType = \Epay\Magento2EpicPaymentModule\Block\Info\Epay::class;
    protected $_isOffline = false;
    protected $_isGateway = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseCheckout = true;
    protected $_isInitializeNeeded = true;

    private EpayHandler $epayHandler;
    private LoggerInterface $psrLogger;
    protected ScopeConfigInterface $scopeConfig;

    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        PaymentHelper $paymentData,
        ScopeConfigInterface $scopeConfig,
        MethodLogger $methodLogger,
        EpayHandler $epayHandler,
        LoggerInterface $psrLogger,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $methodLogger, $resource, $resourceCollection, $data);

        $this->scopeConfig = $scopeConfig;
        $apikey = $this->scopeConfig->getValue('payment/' . self::SHARED_CONFIG_CODE . '/apikey', ScopeInterface::SCOPE_STORE);
        $this->epayHandler = $epayHandler;
        $this->epayHandler->setAuthData($apikey);
        $this->psrLogger = $psrLogger;
    }

    public function initialize($paymentAction, $stateObject)
    {
        $stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);

        return $this;
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount): self
    {
        $this->psrLogger->info('Start authorize');

        return $this;
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount): self
    {
        $order = $payment->getOrder();
        if ($order && (float)$payment->getAmountPaid() >= (float)$order->getGrandTotal()) {
            return $this;
        }

        $this->epayHandler->capture($payment->getLastTransId(), $this->convertAmountToMinorunits($amount));
        $payment->setTransactionId($payment->getLastTransId());
        $payment->setIsTransactionClosed(true);

        return $this;
    }

    public function canCapture()
    {
        $payment = $this->getData('info_instance');
        $order = $payment ? $payment->getOrder() : null;
        if ($order && (float)$payment->getAmountPaid() >= (float)$order->getGrandTotal()) {
            return false;
        }

        return parent::canCapture();
    }

    public function canVoid()
    {
        $payment = $this->getData('info_instance');
        if (!$payment) {
            return parent::canVoid();
        }

        $order = $payment->getOrder();
        if ($order && (float)$payment->getAmountPaid() >= (float)$order->getGrandTotal()) {
            return false;
        }

        return parent::canVoid();
    }

    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount): self
    {
        $order = $payment->getOrder();
        $lastTxId = $payment->getLastTransId();
        $this->psrLogger->info('Starting refund', ['order' => $order ? $order->getIncrementId() : null, 'amount' => $amount, 'parent_tx_id' => $lastTxId]);

        $this->epayHandler->refund($lastTxId, $this->convertAmountToMinorunits($amount));
        $payment->setTransactionId($lastTxId)->setParentTransactionId($lastTxId)->setIsTransactionClosed(1)->addTransaction(PaymentTransaction::TYPE_REFUND);
        $payment->registerRefundNotification($amount);

        return $this;
    }

    public function cancel(\Magento\Payment\Model\InfoInterface $payment): self
    {
        return $this->void($payment);
    }

    public function void(\Magento\Payment\Model\InfoInterface $payment): self
    {
        $lastTxId = $payment->getLastTransId();
        $this->psrLogger->info('Void start', ['transaction_id' => $lastTxId]);
        $this->epayHandler->void($lastTxId, -1);

        $payment->setTransactionId($lastTxId)->setParentTransactionId($lastTxId)->setIsTransactionClosed(1)->addTransaction(PaymentTransaction::TYPE_VOID);

        return $this;
    }

    public function isAvailable(?\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (!parent::isAvailable($quote) || !$quote) {
            return parent::isAvailable($quote);
        }

        $storeId = (int)$quote->getStoreId();
        if (!$this->isCurrencyAllowed((string)$quote->getQuoteCurrencyCode(), $storeId)) {
            return false;
        }

        return $this->isCountryAllowed($quote, $storeId);
    }

    protected function isCurrencyAllowed(string $currencyCode, int $storeId): bool
    {
        $configuredCurrencies = trim((string)$this->scopeConfig->getValue('payment/' . $this->_code . '/allowed_currencies', ScopeInterface::SCOPE_STORE, $storeId));
        if ($configuredCurrencies === '') {
            return true;
        }

        return in_array($currencyCode, array_map('trim', explode(',', $configuredCurrencies)), true);
    }

    private function isCountryAllowed(\Magento\Quote\Api\Data\CartInterface $quote, int $storeId): bool
    {
        $allowSpecific = (string)$this->scopeConfig->getValue('payment/' . $this->_code . '/allowspecific', ScopeInterface::SCOPE_STORE, $storeId);
        if ($allowSpecific !== '1') {
            return true;
        }

        $address = $quote->getBillingAddress();
        $countryId = $address ? (string)$address->getCountryId() : '';
        if ($countryId === '') {
            $shippingAddress = $quote->getShippingAddress();
            $countryId = $shippingAddress ? (string)$shippingAddress->getCountryId() : '';
        }
        if ($countryId === '') {
            return true;
        }

        $specificCountries = (string)$this->scopeConfig->getValue('payment/' . $this->_code . '/specificcountry', ScopeInterface::SCOPE_STORE, $storeId);

        return in_array($countryId, array_map('trim', explode(',', $specificCountries)), true);
    }

    private function convertAmountToMinorunits($amount): int
    {
        return (int)round(((float)$amount) * 100);
    }
}
