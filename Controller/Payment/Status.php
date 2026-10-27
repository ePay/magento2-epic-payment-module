<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Controller\Payment;

use Epay\Magento2EpicPaymentModule\Model\Payment\PaymentRegistrationChecker;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class Status extends Action implements HttpGetActionInterface
{
    private CheckoutSession $checkoutSession;
    private OrderRepositoryInterface $orderRepository;
    private JsonFactory $resultJsonFactory;
    private PaymentRegistrationChecker $paymentRegistrationChecker;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        JsonFactory $resultJsonFactory,
        PaymentRegistrationChecker $paymentRegistrationChecker
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->paymentRegistrationChecker = $paymentRegistrationChecker;
    }

    public function execute()
    {
        $status = 'pending';
        $orderId = (int)$this->checkoutSession->getLastOrderId();

        if ($orderId > 0) {
            try {
                $order = $this->orderRepository->get($orderId);
                if ($this->paymentRegistrationChecker->isPaymentRegistered($order)) {
                    $status = 'paid';
                }
            } catch (\Exception $exception) {
                $status = 'pending';
            }
        }

        $result = $this->resultJsonFactory->create()->setData(['status' => $status]);
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $result->setHeader('Pragma', 'no-cache', true);

        return $result;
    }
}
