<?php
namespace Dna\Payment\Block;

/**
 * Class Info
 *
 * @package Dna\Payment\Block
 */
class Info extends \Magento\Payment\Block\Info
{

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_orderFactory = $orderFactory;
    }

    /**
     * Prepare information specific to current payment method
     *
     * @param null | array $transport
     * @return \Magento\Framework\DataObject
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $data = [];

        $info = $this->getInfo();
        $additionalInfo = $info->getAdditionalInformation();
        $paymentData = isset($additionalInfo['paymentResponse']) ? $additionalInfo['paymentResponse'] : $additionalInfo;

        if (isset($paymentData['invoiceId'])) {
            $title = __('DNA Payments order number');
            $data[$title->__toString()] = $paymentData['invoiceId'];
        }

        if (isset($paymentData['id'])) {
            $title = __('Transaction id ');
            $data[$title->__toString()] = $paymentData['id'];
        }

        if (isset($paymentData['rrn'])) {
            $title = __('Reference ');
            $data[$title->__toString()] = $paymentData['rrn'];
        }

        if (isset($paymentData['paymentMethod'])) {
            $title = __('Payment method ');
            $data[$title->__toString()] = $paymentData['paymentMethod'];
        }

        if (isset($paymentData['message'])) {
            $title = __('Message ');
            $data[$title->__toString()] = $paymentData['message'];
        }

        if (isset($paymentData['paypalOrderStatus'])) {
            $title = __('Paypal order status ');
            $data[$title->__toString()] = $paymentData['paypalOrderStatus'];
        }

        if (isset($paymentData['paypalCaptureStatus'])) {
            $title = __('Paypal capture status ');
            $data[$title->__toString()] = $paymentData['paypalCaptureStatus'];
        }

        if (isset($paymentData['paypalCaptureStatusReason'])) {
            $title = __('Paypal capture status reason ');
            $data[$title->__toString()] = $paymentData['paypalCaptureStatusReason'];
        }

        return $transport->setData(array_merge($data, $transport->getData()));
    }
}
