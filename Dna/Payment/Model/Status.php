<?php
namespace Dna\Payment\Model;

/**
 * Overrides array to avoid showing certain statuses as an option
 * Class Status
 *
 * @package Dna\Payment\Model
 */
class Status
    extends \Magento\Sales\Model\Config\Source\Order\Status
{
    /**
     * @var string[]
     */
    protected $_stateStatuses = null;

}
