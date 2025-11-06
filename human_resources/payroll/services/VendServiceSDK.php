<?php
declare(strict_types=1);

namespace PayrollModule\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PayrollModule\Lib\PayrollLogger;

/**
 * Vend Service - Official API Implementation
 *
 * High-quality implementation using Vend/Lightspeed Retail API
 * Handles all Vend operations including:
 * - Sales and transactions
 * - Products and inventory
 * - Customers
 * - Registers and outlets
 * - Account payments and snapshots
 * - Comprehensive error handling and logging
 * - Rate limiting and retry logic
 *
 * @package PayrollModule\Services
 * @version 2.0.0 (Official API)
 * @see https://developers.lightspeedhq.com/retail/introduction/introduction/
 */
class VendServiceSDK extends BaseService
{
    private string $apiEndpoint;
    private string $accessToken;
    private Client $httpClient;

    // API configuration
    private const API_VERSION = '2.0';
    private const DEFAULT_TIMEOUT = 30;
    private const CONNECT_TIMEOUT = 10;
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 500;

    // Rate limiting (Vend allows 10,000 requests/day, ~7 requests/minute)
    private const RATE_LIMIT_REQUESTS = 7;
    private const RATE_LIMIT_WINDOW_MS = 60000; // 1 minute
    private array $requestTimestamps = [];

    // Snapshot configuration
    private const DEFAULT_REGISTER_NAME = 'Hamilton East';
    private const DEFAULT_PAYMENT_TYPE_NAME = 'Internet Banking';
    private const FALLBACK_REGISTER_ID = 'efdf9bc5-20b8-11e4-8c21-b8ca3a64f8f4';
    private const FALLBACK_PAYMENT_TYPE_ID = '5';

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        // Load Vend configuration from environment
        $domainPrefix = $_ENV['VEND_DOMAIN_PREFIX'] ?? '';
        $this->accessToken = $_ENV['VEND_ACCESS_TOKEN'] ?? '';

        if (empty($domainPrefix) || empty($this->accessToken)) {
            $this->logger->error('Vend credentials not configured', [
                'has_domain' => !empty($domainPrefix),
                'has_token' => !empty($this->accessToken)
            ]);
            throw new \RuntimeException('Vend credentials not configured');
        }

        $this->apiEndpoint = "https://{$domainPrefix}.vendhq.com/api";

        // Initialize HTTP client
        $this->httpClient = new Client([
            'base_uri' => $this->apiEndpoint,
            'timeout' => self::DEFAULT_TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'CIS-Payroll/2.0'
            ]
        ]);

        $this->logger->info('VendServiceSDK initialized', [
            'endpoint' => $this->apiEndpoint
        ]);
    }

    /**
     * Rate limit requests to comply with Vend API limits
     */
    private function rateLimit(): void
    {
        $now = microtime(true) * 1000; // Convert to milliseconds

        // Remove timestamps outside the current window
        $this->requestTimestamps = array_filter(
            $this->requestTimestamps,
            fn($ts) => ($now - $ts) < self::RATE_LIMIT_WINDOW_MS
        );

        // If we've hit the limit, wait
        if (count($this->requestTimestamps) >= self::RATE_LIMIT_REQUESTS) {
            $oldestRequest = min($this->requestTimestamps);
            $waitMs = self::RATE_LIMIT_WINDOW_MS - ($now - $oldestRequest);

            if ($waitMs > 0) {
                $this->logger->debug('Rate limiting: waiting', [
                    'wait_ms' => $waitMs
                ]);
                usleep((int)($waitMs * 1000));
            }
        }

        // Record this request
        $this->requestTimestamps[] = microtime(true) * 1000;
    }

    /**
     * Make API request with retry logic
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param array $query Query parameters
     * @return array Response data
     * @throws \RuntimeException On API error
     */
    private function apiRequest(
        string $method,
        string $endpoint,
        array $data = [],
        array $query = []
    ): array {
        $this->rateLimit();

        $startTime = $this->logger->startTimer('vend_api_request');
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $attempt++;

                $options = [];
                if (!empty($query)) {
                    $options['query'] = $query;
                }
                if (!empty($data)) {
                    $options['json'] = $data;
                }

                $this->logger->debug('Vend API request', [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'has_data' => !empty($data),
                    'query_params' => array_keys($query)
                ]);

                $response = $this->httpClient->request($method, $endpoint, $options);
                $body = $response->getBody()->getContents();
                $statusCode = $response->getStatusCode();

                $this->logger->debug('Vend API response', [
                    'status_code' => $statusCode,
                    'response_size' => strlen($body)
                ]);

                $this->logger->endTimer($startTime, 'vend_api_request');

                $result = json_decode($body, true);

                return $result ?? [];

            } catch (GuzzleException $e) {
                $this->logger->warning('Vend API request failed', [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);

                // If this was the last attempt, throw
                if ($attempt >= self::MAX_RETRIES) {
                    $this->logger->error('Vend API request failed after retries', [
                        'method' => $method,
                        'endpoint' => $endpoint,
                        'attempts' => $attempt,
                        'error' => $e->getMessage()
                    ]);
                    throw new \RuntimeException('Vend API request failed: ' . $e->getMessage());
                }

                // Wait before retrying
                usleep(self::RETRY_DELAY_MS * $attempt * 1000);
            }
        }

        throw new \RuntimeException('Vend API request failed after all retries');
    }

    /**
     * Get all products with pagination
     *
     * @param int $page Page number (1-based)
     * @param int $pageSize Items per page (max 10000)
     * @return array Product data with pagination info
     */
    public function getProducts(int $page = 1, int $pageSize = 200): array
    {
        $startTime = $this->logger->startTimer('vend_get_products');

        try {
            $this->logger->info('Fetching products from Vend', [
                'page' => $page,
                'page_size' => $pageSize
            ]);

            $query = [
                'page' => $page,
                'page_size' => min($pageSize, 10000)
            ];

            $result = $this->apiRequest('GET', '/2.0/products', [], $query);

            $products = $result['data'] ?? [];
            $pagination = $result['version'] ?? [];

            $this->logger->info('Products fetched successfully', [
                'count' => count($products),
                'page' => $page,
                'has_more' => !empty($pagination['max'])
            ]);

            $this->logger->endTimer($startTime, 'vend_get_products');

            return [
                'products' => $products,
                'pagination' => $pagination
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch products', [
                'page' => $page,
                'error' => $e->getMessage()
            ]);
            return ['products' => [], 'pagination' => []];
        }
    }

    /**
     * Get product by ID
     *
     * @param string $productId Vend product ID
     * @return array|null Product data
     */
    public function getProduct(string $productId): ?array
    {
        $startTime = $this->logger->startTimer('vend_get_product');

        try {
            $this->logger->info('Fetching product from Vend', [
                'product_id' => $productId
            ]);

            $result = $this->apiRequest('GET', "/2.0/products/{$productId}");

            $product = $result['data'] ?? null;

            if ($product) {
                $this->logger->info('Product fetched successfully', [
                    'product_id' => $productId,
                    'name' => $product['name'] ?? 'Unknown'
                ]);
            }

            $this->logger->endTimer($startTime, 'vend_get_product');

            return $product;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch product', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get sales for date range
     *
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param string|null $registerId Optional register filter
     * @return array List of sales
     */
    public function getSales(
        string $startDate,
        string $endDate,
        ?string $registerId = null
    ): array {
        $startTime = $this->logger->startTimer('vend_get_sales');

        try {
            $this->logger->info('Fetching sales from Vend', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'register_id' => $registerId
            ]);

            $query = [
                'since' => $startDate . 'T00:00:00Z',
                'before' => $endDate . 'T23:59:59Z'
            ];

            if ($registerId) {
                $query['register_id'] = $registerId;
            }

            $result = $this->apiRequest('GET', '/2.0/sales', [], $query);

            $sales = $result['data'] ?? [];

            $this->logger->info('Sales fetched successfully', [
                'count' => count($sales),
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

            $this->logger->endTimer($startTime, 'vend_get_sales');

            return $sales;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch sales', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get all registers
     *
     * @return array List of registers
     */
    public function getRegisters(): array
    {
        $startTime = $this->logger->startTimer('vend_get_registers');

        try {
            $this->logger->info('Fetching registers from Vend');

            $result = $this->apiRequest('GET', '/2.0/registers');

            $registers = $result['data'] ?? [];

            $this->logger->info('Registers fetched successfully', [
                'count' => count($registers)
            ]);

            $this->logger->endTimer($startTime, 'vend_get_registers');

            return $registers;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch registers', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get register by name or ID
     *
     * @param string $identifier Register name or ID
     * @return array|null Register data
     */
    public function getRegister(string $identifier): ?array
    {
        $registers = $this->getRegisters();

        foreach ($registers as $register) {
            if (
                $register['id'] === $identifier ||
                $register['name'] === $identifier ||
                strcasecmp($register['name'] ?? '', $identifier) === 0
            ) {
                $this->logger->info('Register found', [
                    'identifier' => $identifier,
                    'register_id' => $register['id'],
                    'register_name' => $register['name']
                ]);
                return $register;
            }
        }

        $this->logger->warning('Register not found', [
            'identifier' => $identifier
        ]);

        return null;
    }

    /**
     * Get all payment types
     *
     * @return array List of payment types
     */
    public function getPaymentTypes(): array
    {
        $startTime = $this->logger->startTimer('vend_get_payment_types');

        try {
            $this->logger->info('Fetching payment types from Vend');

            $result = $this->apiRequest('GET', '/2.0/payment_types');

            $paymentTypes = $result['data'] ?? [];

            $this->logger->info('Payment types fetched successfully', [
                'count' => count($paymentTypes)
            ]);

            $this->logger->endTimer($startTime, 'vend_get_payment_types');

            return $paymentTypes;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch payment types', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get payment type by name or ID
     *
     * @param string $identifier Payment type name or ID
     * @return array|null Payment type data
     */
    public function getPaymentType(string $identifier): ?array
    {
        $paymentTypes = $this->getPaymentTypes();

        foreach ($paymentTypes as $paymentType) {
            if (
                $paymentType['id'] === $identifier ||
                $paymentType['name'] === $identifier ||
                strcasecmp($paymentType['name'] ?? '', $identifier) === 0
            ) {
                $this->logger->info('Payment type found', [
                    'identifier' => $identifier,
                    'payment_type_id' => $paymentType['id'],
                    'payment_type_name' => $paymentType['name']
                ]);
                return $paymentType;
            }
        }

        $this->logger->warning('Payment type not found', [
            'identifier' => $identifier
        ]);

        return null;
    }

    /**
     * Get account payments for date range
     *
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Account payments with metadata
     */
    public function getAccountPayments(string $startDate, string $endDate): array
    {
        $startTime = $this->logger->startTimer('vend_get_account_payments');

        try {
            $this->logger->info('Fetching account payments', [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

            // Get register ID
            $register = $this->getRegister(self::DEFAULT_REGISTER_NAME);
            $registerId = $register['id'] ?? self::FALLBACK_REGISTER_ID;

            // Get payment type ID
            $paymentType = $this->getPaymentType(self::DEFAULT_PAYMENT_TYPE_NAME);
            $paymentTypeId = $paymentType['id'] ?? self::FALLBACK_PAYMENT_TYPE_ID;

            // Fetch sales
            $sales = $this->getSales($startDate, $endDate, $registerId);

            // Filter for account payments
            $accountPayments = [];
            foreach ($sales as $sale) {
                foreach ($sale['payments'] ?? [] as $payment) {
                    if ($payment['payment_type_id'] === $paymentTypeId) {
                        $accountPayments[] = [
                            'sale_id' => $sale['id'],
                            'customer_id' => $sale['customer_id'] ?? null,
                            'amount' => $payment['amount'] ?? 0,
                            'payment_date' => $payment['payment_date'] ?? $sale['sale_date'],
                            'payment' => $payment,
                            'sale' => $sale
                        ];
                    }
                }
            }

            $this->logger->info('Account payments fetched successfully', [
                'count' => count($accountPayments),
                'total_amount' => array_sum(array_column($accountPayments, 'amount'))
            ]);

            $this->logger->endTimer($startTime, 'vend_get_account_payments');

            return [
                'payments' => $accountPayments,
                'register_id' => $registerId,
                'payment_type_id' => $paymentTypeId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch account payments', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            return ['payments' => []];
        }
    }

    /**
     * Test connection to Vend API
     *
     * @return array Test result with details
     */
    public function testConnection(): array
    {
        $startTime = $this->logger->startTimer('vend_test_connection');

        try {
            $this->logger->info('Testing Vend API connection');

            // Try to fetch outlets (lightweight endpoint)
            $result = $this->apiRequest('GET', '/2.0/outlets');

            $outlets = $result['data'] ?? [];

            if (!empty($outlets)) {
                $this->logger->info('Vend connection test successful', [
                    'outlet_count' => count($outlets)
                ]);

                $this->logger->endTimer($startTime, 'vend_test_connection');

                return [
                    'success' => true,
                    'outlets' => $outlets
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'No outlets found'
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->error('Vend connection test failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get snapshot directories for Vend account payments
     *
     * @return array List of snapshot directory paths
     */
    public function getSnapshotDirectories(): array
    {
        $dirs = [];

        // Check environment variable for custom paths
        $envDirs = getenv('PAYROLL_SNAPSHOT_DIRS');
        if ($envDirs) {
            $decoded = json_decode((string)$envDirs, true);
            if (is_array($decoded)) {
                foreach ($decoded as $d) {
                    if (is_dir((string)$d)) {
                        $dirs[] = rtrim((string)$d, '/');
                    }
                }
            }
        }

        // Check private_html location
        $privateBase = defined('BASE_PATH')
            ? BASE_PATH . '../private_html/modules/payroll_snapshot/snapshots/vend_account_payments/'
            : $_SERVER['DOCUMENT_ROOT'] . '/../private_html/modules/payroll_snapshot/snapshots/vend_account_payments/';

        if (is_dir($privateBase)) {
            $dirs[] = rtrim(realpath($privateBase), '/');
        }

        // Check public_html location (fallback)
        $publicBase = defined('BASE_PATH')
            ? BASE_PATH . 'modules/payroll_snapshot/snapshots/vend_account_payments/'
            : $_SERVER['DOCUMENT_ROOT'] . '/modules/payroll_snapshot/snapshots/vend_account_payments/';

        if (is_dir($publicBase)) {
            $dirs[] = rtrim(realpath($publicBase), '/');
        }

        $this->logger->info('Snapshot directories located', [
            'count' => count($dirs),
            'directories' => $dirs
        ]);

        return array_values(array_unique($dirs));
    }
}
