<?php
namespace Dna\Payment\Block;

use Magento\Framework\View\Element\Template;

/**
 * Class Failure
 * @package Dna\Payment\Block
 */
class Failure extends Template
{
    /**
     * @return string
     */
    public function getErrorMessage()
    {
        return 'Sorry, we were unable to process your payment. Please try again.';
    }

    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getUrlHome()
    {
        return $this->_storeManager->getStore()->getBaseUrl();
    }
}
