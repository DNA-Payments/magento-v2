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
      $paymentResponse = $info->getAdditionalInformation("paymentResponse");

    if(isset($paymentResponse['id'])){
        $title = __('Payment id: ');
        $data[$title->__toString()] = $paymentResponse['id'];
    }

    if(isset($paymentResponse['reference'])){
        $title = __('Reference: ');
        $data[$title->__toString()] = $paymentResponse['reference'];
    }

    if(isset($paymentResponse['amount'])){
        $title = __('Amount: ');
        $data[$title->__toString()] = $paymentResponse['amount'];
    }

    if(isset($paymentResponse['message'])){
        $title = __('Message: ');
        $data[$title->__toString()] = $paymentResponse['message'];
    }

    return $transport->setData(array_merge($data, $transport->getData()));
    }

}
