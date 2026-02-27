<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Model;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;

use Magento\Store\Model\ScopeInterface;

use Magento\Payment\Model\Method\Logger as MethodLogger;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Payment\Model\Method\AbstractMethod;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;
use Epay\Magento2EpicPaymentModule\Model\Payment\EpayHandler;

use Magento\Payment\Model\InfoInterface;

class PaymentMethod extends AbstractMethod
{       
    protected $_code = 'epayepicpayment';
    protected $_infoBlockType = \Epay\Magento2EpicPaymentModule\Block\Info\Epay::class;

    protected $_isOffline = false;
    protected $_isGateway = true;
    // protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    // protected $_canUseInternal = true; // false
    protected $_canUseCheckout = true;
    // protected $_canUseForMultishipping = true; // false
    // protected $_canFetchTransactionInfo = false;
    // protected $_canReviewPayment = false;
    // protected $_canManageRecurringProfiles = false;

    private EpayHandler $epayHandler;
    private LoggerInterface $psrLogger;
    protected $_isInitializeNeeded = true;
    private ScopeConfigInterface $scopeConfig;

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
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $methodLogger,
            $resource,
            $resourceCollection,
            $data
        );

        $this->scopeConfig = $scopeConfig;

        $apikey = $this->scopeConfig->getValue(
            'payment/epayepicpayment/apikey',
            ScopeInterface::SCOPE_STORE
        );
        
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

    /**
     * Authorize
     *
     * @param InfoInterface $payment
     * @param float         $amount
     * @return $this
     */
    public function authorize(InfoInterface $payment, $amount): self
    {

        $this->psrLogger->info('Start authorize ', ['Result' => true]);
        // $payment->setTransactionId('1234'.rand(1000, 9999));
        // $payment->setIsTransactionClosed(false);
        return $this;
    }

    /**
     * Capture
     *
     * @param InfoInterface $payment
     * @param float         $amount
     * @return $this
     */
    public function capture(InfoInterface $payment, $amount): self
    {
        $order = $payment->getOrder();

        $captureResult = $this->epayHandler->capture($payment->getLastTransId(), $this->convertAmountToMinorunits($amount));

        $payment->setTransactionId($payment->getLastTransId());
        $payment->setIsTransactionClosed(true);

        return $this;
    }

    /**
     * Refund
     *
     * @param InfoInterface $payment
     * @param float         $amount
     * @return $this
     * @throws LocalizedException
     */
    public function refund(InfoInterface $payment, $amount): self
    {
        $order    = $payment->getOrder();
        $lastTxId = $payment->getLastTransId();

        $this->psrLogger->error('Starter refund', [
            'order'         => $order->getIncrementId(),
            'amount'        => $amount,
            'parent_tx_id'  => $lastTxId,
        ]);

        try {
            $refundResult = $this->epayHandler->refund($lastTxId, $this->convertAmountToMinorunits($amount));

            $payment->setTransactionId($lastTxId)
                    ->setParentTransactionId($lastTxId)
                    ->setIsTransactionClosed(1)
                    ->addTransaction(PaymentTransaction::TYPE_REFUND);

            $payment->registerRefundNotification($amount);

            $this->psrLogger->info('Refund completed', [
                'amount' => $amount,
                'refund_tx_id' => $lastTxId
            ]);
        } catch (\Throwable $e) {
            $this->psrLogger->critical('Exception under refund', ['exception' => $e->getMessage()]);
            throw $e;
        }

        return $this;
    }

    /**
     * Cancel
     *
     * @param InfoInterface $payment
     * @return $this
     */
    public function cancel(InfoInterface $payment): self
    {
        $this->void($payment);
        return $this;
    }

    /**
     * Void
     *
     * @param InfoInterface $payment
     * @return $this
     */ 
    public function void(InfoInterface $payment): self
    {
        $lastTxId = $payment->getLastTransId();
        $this->psrLogger->info('Void start', ['transaction_id' => $lastTxId]);

        $voidResult = $this->epayHandler->void($lastTxId, -1);
        
        $payment->setTransactionId($lastTxId)
                ->setParentTransactionId($lastTxId)
                ->setIsTransactionClosed(1)
                ->addTransaction(PaymentTransaction::TYPE_VOID);

        return $this;
    }

    /*
    public function getOrderPlaceRedirectUrl()
    {
        // return '/epay/payment/redirect';
        // return $this->_urlBuilder->getUrl('epay/payment/redirect', ['_secure' => true]);
    }
    */

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return parent::isAvailable($quote);
    }

    private function convertAmountToMinorunits($amount): int
    {
        return (int) round(((float)$amount) * 100);
    }
}
