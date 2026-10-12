<?php

namespace Dna\Payment\Model;

use Dna\Payment\Gateway\Config\Config;
use Dna\Payment\Helper\DnaLogger;
use GuzzleHttp\Client;
use Magento\Sales\Model\Order;

/**
 * Asks DNA what happened to an order's payment before the module cancels the order or reopens its cart.
 *
 * DNA returns every transaction with this invoiceId on the account, including other shops' attempts,
 * so only transactions with the order's amount and currency, created after the order, are taken into account.
 */
class TransactionStatus
{
    const PAID = 'paid';
    const FAILED = 'failed';
    const NOT_FOUND = 'not_found';
    const UNKNOWN = 'unknown';

    /** Final states that mean the payment did not and will not go through. */
    const FINAL_FAILED_STATES = ['REJECT', 'CANCEL', 'ABANDONED'];

    /** A 3DS attempt older than this is treated as abandoned. */
    const THREE_DS_TIMEOUT_SECONDS = 900;

    /**
     * The check runs while the customer waits for Checkout, so DNA gets a hard time limit per request.
     * The SDK's own HTTP client has none; the same two SDK calls are made here directly.
     */
    const CONNECT_TIMEOUT_SECONDS = 2;
    const REQUEST_TIMEOUT_SECONDS = 4;

    /** @var Config */
    private $config;

    /** @var DnaLogger */
    private $dnaLogger;

    public function __construct(Config $config, DnaLogger $dnaLogger)
    {
        $this->config = $config;
        $this->dnaLogger = $dnaLogger;
    }

    /**
     * @return string PAID, FAILED (final decline/void), NOT_FOUND (no attempt for this order)
     *                or UNKNOWN (in progress, unrecognised or DNA could not be asked).
     */
    public function check(Order $order)
    {
        $storeId = $order->getStoreId();
        $isTestMode = $this->config->getTestMode($storeId);

        try {
            $http = new Client([
                'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
            ]);
            // Same requests as DNAPayments::get_client_token() and ::get_transactions_by_invoice_id() in SDK 2.1.2.
            $token = json_decode((string) $http->post(
                $isTestMode ? 'https://test-oauth.dnapayments.com/oauth2/token' : 'https://oauth.dnapayments.com/oauth2/token',
                ['form_params' => [
                    'grant_type' => 'client_credentials',
                    'scope' => 'webapi',
                    'client_id' => $isTestMode ? $this->config->getClientIdTest($storeId) : $this->config->getClientId($storeId),
                    'client_secret' => $isTestMode ? $this->config->getClientSecretTest($storeId) : $this->config->getClientSecret($storeId),
                ]]
            )->getBody(), true);
            $transactions = json_decode((string) $http->get(
                ($isTestMode ? 'https://test-api.dnapayments.com' : 'https://api.dnapayments.com')
                    . '/v2/transactions/' . rawurlencode((string) $order->getIncrementId()) . '/list?field=invoiceId',
                ['headers' => [
                    'Authorization' => 'Bearer ' . ($token['access_token'] ?? ''),
                    'Accept' => 'application/json',
                ]]
            )->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getCode() === 404) {
                return self::NOT_FOUND;
            }
            return $this->unknown($order, 'request failed: ' . $e->getCode());
        } catch (\Throwable $e) {
            return $this->unknown($order, 'request failed: ' . get_class($e));
        }

        if ($transactions === []) {
            return self::NOT_FOUND;
        }
        if (!is_array($transactions)) {
            return $this->unknown($order, 'unexpected response');
        }

        $orderAmount = round((float) $order->getGrandTotal(), 2);
        $orderCurrency = (string) $order->getOrderCurrencyCode();
        $orderCreated = strtotime((string) $order->getCreatedAt() . ' UTC') ?: 0;

        $states = [];
        foreach ($transactions as $transaction) {
            if (!is_array($transaction) || !isset($transaction['transactionState'], $transaction['amount'], $transaction['currency'])) {
                return $this->unknown($order, 'unexpected transaction format');
            }
            $created = strtotime((string) ($transaction['createdDateTimeUTC'] ?? '')) ?: 0;
            if (round((float) $transaction['amount'], 2) !== $orderAmount
                || (string) $transaction['currency'] !== $orderCurrency
                || ($created && $orderCreated && $created < $orderCreated - 60)
            ) {
                continue; // another order or shop that used the same invoiceId
            }
            $state = strtoupper((string) $transaction['transactionState']);
            if ($state === '3D' && $created && time() - $created > self::THREE_DS_TIMEOUT_SECONDS) {
                $state = 'ABANDONED';
            }
            $states[] = $state;
        }

        $this->dnaLogger->info('DNA transaction status', [
            'order_id' => $order->getIncrementId(),
            'states' => $states,
        ]);

        if (!$states) {
            return self::NOT_FOUND;
        }
        if (in_array('CANCEL', $states, true)) {
            return self::FAILED; // the authorisation was voided
        }
        if (array_intersect($states, ['CHARGE', 'AUTH'])) {
            return self::PAID; // refunds (CREDITED) do not make a paid order unpaid
        }
        if (!array_diff($states, self::FINAL_FAILED_STATES)) {
            return self::FAILED;
        }
        return $this->unknown($order, 'payment in progress or unrecognised state');
    }

    private function unknown(Order $order, $reason)
    {
        $this->dnaLogger->warning('DNA transaction status unknown', [
            'order_id' => $order->getIncrementId(),
            'reason' => $reason,
        ]);
        return self::UNKNOWN;
    }
}
