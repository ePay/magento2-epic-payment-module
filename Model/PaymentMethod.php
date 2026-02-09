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
    protected $_canAuthorize = true;
    protected $_canCapture = false;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = true;
    // protected $_canUseInternal = true; // false
    protected $_canUseCheckout = true;
    // protected $_canUseForMultishipping = true; // false
    // protected $_canFetchTransactionInfo = false;
    // protected $_canReviewPayment = false;
    // protected $_canManageRecurringProfiles = false;

    private EpayHandler $epayHandler;
    private $psrLogger;
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
        $this->logger   = $psrLogger;
    }

    public function initialize($paymentAction, $stateObject)
    {
        $stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);
    }

    /**
     * Authorize
     *
     * @param InfoInterface $payment
     * @param float         $amount
     * @return $this
     */
    public function authorize(InfoInterface $payment, $amount)
    {

        $this->logger->info('Start authorize ', ['Result' => true]);
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

        $this->logger->error('Starter refund', [
            'order'         => $order->getIncrementId(),
            'amount'        => $amount,
            'parent_tx_id'  => $lastTxId,
        ]);

        try {
            $refundResult = $this->epayHandler->refund($lastTxId, $this->convertAmountToMinorunits($amount));

            /*
            if (!$response->isSuccess()) {
                $this->logger->error('Refund fejlede', [
                    'error'     => $response->getErrorMessage(),
                    'response'  => $response->getData()
                ]);
                throw new LocalizedException(
                    __('Refund mislykkedes: %1', $response->getErrorMessage())
                );
            }
            */

            $payment->setTransactionId($lastTxId)
                    ->setParentTransactionId($lastTxId)
                    ->setIsTransactionClosed(1)
                    ->addTransaction(PaymentTransaction::TYPE_CAPTURE);

            $payment->registerRefundNotification($amount);

            $this->logger->info('Refund completed', [
                'amount' => $amount,
                'refund_tx_id' => $lastTxId
            ]);
        } catch (\Throwable $e) {
            $this->logger->critical('Exception under refund', ['exception' => $e->getMessage()]);
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
        $this->logger->info('Void start', ['transaction_id' => $lastTxId]);

        $voidResult = $this->epayHandler->void($lastTxId, -1);
        
        /*
        if (!$response->isSuccess()) {
            $this->psrLogger->error('Void failed', [
                'transaction_id' => $lastTxId,
                'error'          => $response->getErrorMessage(),
            ]);
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Void failed: %1', $response->getErrorMessage())
            );
        }
        */

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
        $logger = \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class);
        $logger->info('VENDOR_PAYMENT isAvailable CALLED');

        // return parent::isAvailable($quote); // eller return true for test
        return true;
    }

    private function convertAmountToMinorunits($amount)
    {
        return round($amount*100);
    }
}
