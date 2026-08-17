<?php
namespace Epay\Magento2EpicPaymentModule\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class Accept extends Action
{
    /** @var CheckoutSession */
    protected $checkoutSession;
    /** @var OrderRepositoryInterface */
    protected $orderRepository;
    /** @var PageFactory */
    protected $resultPageFactory;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $orderId = $this->checkoutSession->getLastOrderId();
        if (!$orderId) {
            return $this->_redirect('checkout/cart');
        }

        $order = $this->orderRepository->get($orderId);

        if (!$this->hasConfirmedPayment($order)) {
            $resultPage = $this->resultPageFactory->create();
            $resultPage->getConfig()->getTitle()->set(__('Payment being processed'));

            return $resultPage;
        }

        return $this->_redirect('checkout/onepage/success');
    }

    private function hasConfirmedPayment(OrderInterface $order): bool
    {
        $payment = $order->getPayment();

        return $order->getState() === Order::STATE_PROCESSING
            && (string)$payment->getLastTransId() !== ''
            && (string)$payment->getAdditionalInformation('epay_payment_id') !== '';
    }
}
