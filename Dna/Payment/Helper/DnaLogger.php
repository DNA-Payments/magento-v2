<?php

namespace Dna\Payment\Helper;

use Exception;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

class DnaLogger extends AbstractHelper
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $logFilePath;

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);

        // Initialize Logger
        $this->logger = new Logger('dna_logger');
        $this->logFilePath = BP . '/var/log/dna_payments_logs.log';

        // Set up RotatingFileHandler to rotate logs every hour
        $handler = new RotatingFileHandler($this->logFilePath);
        $this->logger->pushHandler($handler);
    }

    /**
     * Log an info message
     *
     * @param string $message
     * @param array $context
     */
    public function info($message, array $context = [])
    {
        $this->logger->info($message, $context);
    }

    /**
     * Log an error message
     *
     * @param string $message
     * @param array $context
     */
    public function error($message, array $context = [])
    {
        $this->logger->error($message, $context);
    }

    /**
     * Log an exception
     *
     * @param string $message
     * @param Exception $e
     * @param array $context Additional context to include in the log
     */
    public function logException($message, Exception $e, array $context = [])
    {
        $this->logger->error($message, array_merge([
            'error_message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], $context));
    }
}