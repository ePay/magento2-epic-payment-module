<?php
declare(strict_types=1);
namespace Epay\Magento2EpicPaymentModule\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Psr\Log\LoggerInterface;

class Callback extends Action implements CsrfAwareActionInterface
{
    private CheckoutSession $checkoutSession;
    private OrderRepositoryInterface $orderRepository;
    private OrderSender $orderSender;
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        OrderSender $orderSender,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->orderSender = $orderSender;
        $this->logger = $logger;
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
        $rawBodyJson = $this->getRequest()->getContent();
        $postData = json_decode($rawBodyJson, true);

        $this->logger->info('ePay callback modtaget', ['data' => $rawBodyJson]);

        if (!empty($postData['transaction']['reference'])) {
            try {
                $order = $this->orderRepository->get($postData['transaction']['reference']);
                $payment = $order->getPayment();
                $payment->setTransactionId($postData['transaction']['id'])
                ->setIsTransactionClosed(false)
                ->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH);

                $payment->setCcType($postData['transaction']['paymentMethodSubType']);
                $payment->setCcNumberEnc($postData['transaction']['paymentMethodDisplayText']);
                $payment->setAdditionalInformation('epay_callback', $rawBodyJson);
                $payment->save();

                if (isset($postData['transaction']['state']) && $postData['transaction']['state'] === 'SUCCESS') {
                    $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
                          ->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                    $this->orderRepository->save($order);

                    if (!$order->getEmailSent()) {
                        try {
                            $order->setSendEmail(true);
                            $order->setCanSendNewEmailFlag(true);
                            $this->orderSender->send($order, true);
                        } catch (\Exception $e) {
                            $this->logger->error('Fejl ved afsendelse af ordrebekræftelse', [
                                'exception' => $e->getMessage(),
                                'order_id'  => $order->getEntityId()
                            ]);
                        }
                    }
                }

            } catch (\Exception $e) {
                $this->logger->error('Fejl ved behandling af ePay callback', [
                    'exception' => $e->getMessage(),
                    'data'      => $rawBodyJson
                ]);
            }
        } else {
            $this->logger->warning('ePay callback modtaget uden orderid', ['data' => $rawBodyJson]);
        }

        $this->getResponse()->setStatusCode(200);
        return;
    }
}
