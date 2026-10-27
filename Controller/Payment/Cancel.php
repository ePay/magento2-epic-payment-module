<?php
namespace Epay\Magento2EpicPaymentModule\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Store\Model\ScopeInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class Cancel extends Action
{
    /** @var CheckoutSession */
    protected $checkoutSession;

    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->scopeConfig = $scopeConfig;
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
            $storeId = (int)$order->getStoreId();

            $orderStatusAfterCancelPayment = (string)$this->scopeConfig->getValue(
                'payment/epayepicpayment/orderStatusAfterCancelPayment',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

            if ($orderStatusAfterCancelPayment === 'canceled' && $order->canCancel()) {
                $order->cancel();
                $this->orderRepository->save($order);
            }

            $this->checkoutSession->restoreQuote();

            $this->messageManager->addNoticeMessage(
                __('Payment was canceled and your cart has been restored.')
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Payment was canceled, but we could not restore your cart automatically.')
            );
        }

        return $resultRedirect->setPath('checkout/cart');
    }
}