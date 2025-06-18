<?php
namespace Epay\Magento2EpicPaymentModule\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Api\OrderRepositoryInterface;

class Cancel extends Action
{
    /** @var CheckoutSession */
    protected $checkoutSession;
    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
    }

    public function execute()
    {
        $orderId = $this->checkoutSession->getLastOrderId();
        if (!$orderId) {
            return $this->_redirect('checkout/cart');
        }

        $order = $this->orderRepository->get($orderId);

        return $this->_redirect('checkout/cart');
    }
}
