<?php

namespace Dna\Payment\Observer;

use Dna\Payment\Helper\DnaLogger;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Dna\Payment\Helper\DnaAnalytics;
use Magento\Framework\HTTP\Client\Curl;

class DnaAnalyticsObserver implements ObserverInterface
{
    protected $dnaAnalytics;
    protected $dnaLogger;
    protected $curlClient;

    public function __construct(
        DnaAnalytics $dnaAnalytics,
        DnaLogger $dnaLogger,
        Curl $curlClient
    )
    {
        $this->dnaAnalytics = $dnaAnalytics;
        $this->dnaLogger = $dnaLogger;
        $this->curlClient = $curlClient;
    }

    public function execute(Observer $observer)
    {
        try {
            if ($this->dnaAnalytics->hasHashChanged()) {
                $analyticsData = $this->dnaAnalytics->getAnalyticsData();
                $analyticsDataJson = json_encode($analyticsData);
                // TODO: dummy url for testing, replace with actual one
                $url = 'https://webhook.site/170091f1-8f4e-4c18-a0e0-643e94bd2cb1';
                $this->curlClient->addHeader('Content-Type', 'application/json');
                $this->curlClient->post($url, $analyticsDataJson);

                $this->dnaAnalytics->updateHash();
            }
        } catch (\Exception $e) {
            $this->dnaLogger->logException('Failed to process dna analytics event', $e);
        }
    }
}