<?php

namespace Dna\Payment\Block\Adminhtml\Order\View;

class View extends \Magento\Backend\Block\Template
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->registry = $registry;
        parent::__construct($context, $data);
    }
     /**
      * Retrieve order object from registry
      *
      * @return object
      */
      public function getOrder()
    {

        $order = $this->registry->registry('current_order');
        return $order;
    }

    /**
     * Retrieve payment method from order
     *
     * @return String
     */
    public function getPaymentMethod()
    {
        return  $this->getOrder()->getPayment()->getMethod();
    }

    /**
     * check if order is placed through DNA Payments Payment
     *
     * @return Boolean
     */
    public function isDnaPayment()
    {
        $paymentMethod= $this->getPaymentMethod();
        return $paymentMethod === 'dna_payment';
    }
}
