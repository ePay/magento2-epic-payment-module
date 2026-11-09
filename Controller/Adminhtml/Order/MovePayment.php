<?php
declare(strict_types=1);

namespace Epay\Magento2EpicPaymentModule\Controller\Adminhtml\Order;

use Epay\Magento2EpicPaymentModule\Model\Payment\LinkGenerator;
use Epay\Magento2EpicPaymentModule\Model\Payment\PaymentRegistrationChecker;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;

class MovePayment extends Action
{
    public const ADMIN_RESOURCE = 'Epay_Magento2EpicPaymentModule::move_payment';

    private OrderRepositoryInterface $orderRepository;
    private LinkGenerator $linkGenerator;
    private PaymentRegistrationChecker $paymentRegistrationChecker;

    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        LinkGenerator $linkGenerator,
        PaymentRegistrationChecker $paymentRegistrationChecker
    ) {
        parent::__construct($context);
        $this->orderRepository = $orderRepository;
        $this->linkGenerator = $linkGenerator;
        $this->paymentRegistrationChecker = $paymentRegistrationChecker;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $orderId = (int)$this->getRequest()->getParam('order_id');
        $parentOrderId = (int)$this->getRequest()->getParam('parent_order_id');

        try {
            $order = $this->orderRepository->get($orderId);
            $parentOrder = $this->orderRepository->get($parentOrderId);
            $this->assertPaymentCanBeMoved($parentOrder, $order);

            $this->movePayment($parentOrder, $order);
            $this->orderRepository->save($order);
            $this->messageManager->addSuccessMessage(
                __('The ePay payment was moved from order #%1.', $parentOrder->getIncrementId())
            );
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('The ePay payment could not be moved.'));
        }

        return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }

    private function assertPaymentCanBeMoved(Order $parentOrder, Order $order): void
    {
        if ((int)$order->getRelationParentId() !== (int)$parentOrder->getEntityId()) {
            throw new LocalizedException(__('The selected orders are not related.'));
        }

        if ($order->isCanceled() || $parentOrder->isCanceled() === false) {
            throw new LocalizedException(__('The ePay payment can only be moved from a canceled original order.'));
        }

        $parentPayment = $parentOrder->getPayment();
        $payment = $order->getPayment();
        if (
            !$parentPayment
            || !$payment
            || !$this->linkGenerator->supportsPaymentMethod((string)$parentPayment->getMethod())
            || (string)$payment->getMethod() !== (string)$parentPayment->getMethod()
            || !$this->paymentRegistrationChecker->isPaymentRegistered($parentOrder)
        ) {
            throw new LocalizedException(__('The original order does not have a movable ePay payment.'));
        }

        if ((float)$parentPayment->getAmountPaid() > 0.0) {
            throw new LocalizedException(__('A captured ePay payment cannot be moved. Handle it manually in ePay administration.'));
        }

        if ((float)$order->getBaseGrandTotal() > (float)$parentOrder->getBaseGrandTotal()) {
            throw new LocalizedException(__('The edited order exceeds the original payment amount. Handle the payment manually in ePay administration.'));
        }
    }

    private function movePayment(Order $parentOrder, Order $order): void
    {
        $parentPayment = $parentOrder->getPayment();
        $payment = $order->getPayment();
        $transactionId = trim((string)$parentPayment->getLastTransId());

        $payment->setTransactionId($transactionId)
            ->setLastTransId($transactionId)
            ->setIsTransactionClosed(false)
            ->setShouldCloseParentTransaction(false)
            ->setCcType($parentPayment->getCcType())
            ->setCcNumberEnc($parentPayment->getCcNumberEnc())
            ->setAdditionalInformation('epay_payment_id', $transactionId)
            ->setAdditionalInformation('epay_payment_moved_from_order', $parentOrder->getIncrementId())
            ->addTransaction(Transaction::TYPE_AUTH);

        $order->setState(Order::STATE_PROCESSING)
            ->setStatus(Order::STATE_PROCESSING)
            ->addCommentToStatusHistory(
                __('ePay payment moved from order #%1.', $parentOrder->getIncrementId())
            );
    }
}
