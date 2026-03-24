<?php
namespace Epay\Magento2EpicPaymentModule\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Sales\Api\OrderRepositoryInterface;

class Failure extends Action
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
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        $orderId = (int)$this->checkoutSession->getLastOrderId();
        if (!$orderId) {
            return $resultRedirect->setPath('checkout/cart');
        }

        try {
            $order = $this->orderRepository->get($orderId);

            if ($order->canCancel()) {
                $order->cancel();
                $this->orderRepository->save($order);
            }

            $this->messageManager->addErrorMessage(
                __('Payment failed. The order has been canceled.')
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Payment failed.')
            );
        }

        return $resultRedirect->setPath('checkout/cart');
    }
}