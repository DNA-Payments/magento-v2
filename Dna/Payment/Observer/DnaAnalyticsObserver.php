<?php

namespace Dna\Payment\Observer;

use Dna\Payment\Helper\DnaLogger;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Dna\Payment\Helper\DnaAnalytics;
use Magento\Framework\HTTP\Client\Curl;

class DnaAnalyticsObserver implements ObserverInterface
{
    const TELEMETRY_API_URL_TEST = 'https://test-telemetry-api.dnapayments.com/v1/cms-plugins-versions';
    const TELEMETRY_API_URL = 'https://telemetry-api.dnapayments.com/v1/cms-plugins-versions';

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
            $accessToken = $observer->getData('access_token');
            $isTestMode = $observer->getData('is_test_mode');

            if ($accessToken) {
                $analyticsData = $this->dnaAnalytics->getAnalyticsData();
                $response = $this->sendAnalytics($accessToken, $isTestMode, $analyticsData);
                $this->dnaAnalytics->updateHash();
            }
        } catch (\Exception $e) {
            $this->dnaLogger->logException('Failed to process dna analytics event', $e);
        }
    }

    public function sendAnalytics($accessToken, $isTestMode, $analyticsData) {
        try {
            $this->curlClient->addHeader('Content-Type', 'application/json');
            $this->curlClient->addHeader('Authorization', 'Bearer ' . $accessToken);

            $url = $isTestMode ? self::TELEMETRY_API_URL_TEST : self::TELEMETRY_API_URL;
            $this->curlClient->post($url, json_encode($analyticsData));

            $responseBody = $this->curlClient->getBody();
            $responseStatus = $this->curlClient->getStatus();

            if ($responseStatus === 200) {
                return json_decode($responseBody, true);
            } else {
                throw new \Exception('Error: ' . $responseBody);
            }
        } catch (\Exception $e) {
            throw new \Exception('API Request failed: ' . $e->getMessage());
        }
    }
}