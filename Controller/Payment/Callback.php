<?php
declare(strict_types=1);
namespace Epay\Magento2EpicPaymentModule\Controller\Payment;

use Epay\Magento2EpicPaymentModule\Helper\EpayPaymentHelper;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;


class Callback extends Action implements CsrfAwareActionInterface
{
    private CheckoutSession $checkoutSession;
    private OrderRepositoryInterface $orderRepository;
    private OrderSender $orderSender;
    private LoggerInterface $logger;
    private EpayPaymentHelper $epayPaymentHelper;
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        OrderSender $orderSender,
        LoggerInterface $logger,
        EpayPaymentHelper $epayPaymentHelper,
        ScopeConfigInterface $scopeConfig,
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->orderSender = $orderSender;
        $this->logger = $logger;
        $this->epayPaymentHelper = $epayPaymentHelper;
        $this->scopeConfig = $scopeConfig;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): bool
    {
        return true;
    }

    public function execute()
    {
        $authError = $this->validateWebhookAuth();
        if ($authError !== null) {
            $this->logger->warning('ePay callback authorization failed', [
                'reason' => $authError['message'] ?? 'unknown'
            ]);

            return $this->getResponse()
                ->setHeader('Content-Type', 'application/json', true)
                ->setHeader('X-EPay-System', $this->epayPaymentHelper->getModuleHeaderInfo(), true)
                ->setStatusCode(401)
                ->setBody(json_encode($authError));
        }

        $rawBodyJson = $this->getRequest()->getContent();
        $postData = json_decode($rawBodyJson, true);

        $this->logger->info('ePay callback modtaget', ['data' => $rawBodyJson]);

        $responseBody = [
            'success' => true,
            'message' => 'Callback received'
        ];

        try {
            $reference = $postData['transaction']['reference'] ?? '';
            $transactionId = $postData['transaction']['id'] ?? null;
            $state = $postData['transaction']['state'] ?? null;

            if ($reference === '') {
                $this->logger->warning('ePay callback modtaget uden orderid', ['data' => $rawBodyJson]);
                $responseBody = ['success' => false, 'message' => 'Missing order reference'];

                return $this->getResponse()
                    ->setHeader('Content-Type', 'application/json', true)
                    ->setHeader('X-EPay-System', $this->epayPaymentHelper->getModuleHeaderInfo(), true)
                    ->setStatusCode(200)
                    ->setBody(json_encode($responseBody));
            }

            $responseBody = [
                'success' => true,
                'message' => 'Payment updated from callback',
                'reference' => (string) $reference,
                'transactionId' => $transactionId,
                'state' => $state
            ];

            if ($state === 'SUCCESS') {

                $order = $this->orderRepository->get($reference);
                $payment = $order->getPayment();
                $amount  = (float)$order->getBaseGrandTotal();

                if ($transactionId && (string)$payment->getLastTransId() !== (string)$transactionId) {
                    $payment->setTransactionId($transactionId);
                    $payment->registerAuthorizationNotification($amount);
                    $payment->setIsTransactionClosed(false);
                }

                /*
                $payment->setTransactionId($transactionId)
                    ->setIsTransactionClosed(false)
                    ->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH);
                */

                $payment->setCcType($postData['transaction']['paymentMethodSubType'] ?? null);
                $payment->setCcNumberEnc($postData['transaction']['paymentMethodDisplayText'] ?? null);

                $payment->setAdditionalInformation('epay_payment_cardnumber', $postData['transaction']['paymentMethodDisplayText'] ?? null);
                $payment->setAdditionalInformation('epay_payment_subtype', $postData['transaction']['paymentMethodSubType'] ?? null);
                $payment->setAdditionalInformation('epay_payment_expiry', $postData['transaction']['paymentMethodExpiry'] ?? null);
                $payment->setAdditionalInformation('epay_payment_type', $postData['transaction']['paymentMethodType'] ?? null);
                $payment->setAdditionalInformation('epay_payment_posid', $postData['transaction']['pointOfSaleId'] ?? null);
                $payment->setAdditionalInformation('epay_payment_id', $postData['transaction']['id'] ?? null);

                // $payment->setAdditionalInformation('epay_callback', $rawBodyJson);

                $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
                    ->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);

                $this->orderRepository->save($order);

                $responseBody['message'] = 'Order set to processing';
                $responseBody['orderStateChanged'] = true;

                $emailSent = (int)$order->getData('email_sent');

                // if (!$order->getEmailSent()) {
                if ($emailSent !== 1) {

                    $order->setSendEmail(true);
                    $order->setCanSendNewEmailFlag(true);

                    try {
                        $sent = (bool)$this->orderSender->send($order, true);
                        if ($sent) {
                            $responseBody['email'] = 'sent';
                        } else {
                            $responseBody['email'] = 'not_sent';
                        }
                    } catch (\Exception $e) {
                        $this->logger->error('Fejl ved afsendelse af ordrebekræftelse', [
                            'exception' => $e->getMessage(),
                            'order_id'  => $order->getEntityId()
                        ]);

                        $responseBody['email'] = 'failed';
                        $responseBody['emailError'] = $e->getMessage();
                        $responseBody['message'] = 'Order set to processing (email failed)';
                    }
                } else {
                    $responseBody['email'] = 'already_sent';
                }
            } else {
                $responseBody['orderStateChanged'] = false;
                $responseBody['message'] = 'Payment updated (no order state change)';
            }

        } catch (\Exception $e) {
            $this->logger->error('Fejl ved behandling af ePay callback', [
                'exception' => $e->getMessage(),
                'data'      => $rawBodyJson
            ]);

            $responseBody = ['success' => false, 'message' => 'Internal error'];
        }

        return $this->getResponse()
            ->setHeader('Content-Type', 'application/json', true)
            ->setHeader('X-EPay-System', $this->epayPaymentHelper->getModuleHeaderInfo(), true)
            ->setStatusCode(200)
            ->setBody(json_encode($responseBody));
    }

    private function validateWebhookAuth(): ?array
    {
        $configPath = 'payment/epayepicpayment/webhookAuth';

        $expectedToken = (string) $this->scopeConfig->getValue(
            $configPath,
            ScopeInterface::SCOPE_STORE
        );

        $expectedToken = trim($expectedToken);

        if ($expectedToken === '') {
            return null;
        }

        $authHeader =
            $this->getRequest()->getHeader('Authorization')
            ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null)
            ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null);

        $authHeader = trim((string) $authHeader);

        if ($authHeader === '') {
            return ['success' => false, 'message' => 'Missing Authorization header'];
        }

        if (stripos($authHeader, 'Bearer ') === 0) {
            $receivedToken = trim(substr($authHeader, 7));
        } else {
            $receivedToken = $authHeader;
        }

        if ($receivedToken === '') {
            return ['success' => false, 'message' => 'Missing bearer token'];
        }

        if (!hash_equals($expectedToken, $receivedToken)) {
            return ['success' => false, 'message' => 'Invalid webhook token'];
        }

        return null;
    }

}
