<?php
/**
 * FreightNow Provider Implementation
 *
 * Wraps existing FreightNow integration for backward compatibility.
 * Implements FreightProviderInterface for abstraction.
 *
 * @package Consignments\Infrastructure\Freight
 */

declare(strict_types=1);

namespace Consignments\Infrastructure\Freight;

use Consignments\App\Contracts\FreightProviderInterface;
use Psr\Log\LoggerInterface;

class FreightNowProvider implements FreightProviderInterface
{
    private string $apiKey;
    private string $apiUrl;
    private LoggerInterface $logger;
    private array $config;

    public function __construct(
        string $apiKey,
        string $apiUrl,
        LoggerInterface $logger,
        array $config = []
    ) {
        $this->apiKey = $apiKey;
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function createBooking(array $bookingData): array
    {
        $payload = $this->buildBookingPayload($bookingData);

        $response = $this->makeRequest('POST', '/bookings', $payload);

        if (!$response['success']) {
            throw new \RuntimeException(
                "FreightNow booking failed: " . ($response['error'] ?? 'Unknown error')
            );
        }

        $this->logger->info('FreightNow booking created', [
            'booking_reference' => $response['data']['booking_reference'],
            'tracking_number' => $response['data']['tracking_number'],
        ]);

        return [
            'booking_reference' => $response['data']['booking_reference'],
            'tracking_number' => $response['data']['tracking_number'],
            'label_url' => $response['data']['label_url'] ?? null,
            'estimated_delivery' => $response['data']['estimated_delivery'] ?? null,
            'cost' => $response['data']['cost'] ?? null,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getTracking(string $trackingNumber): array
    {
        $response = $this->makeRequest('GET', "/tracking/{$trackingNumber}");

        if (!$response['success']) {
            throw new \RuntimeException(
                "FreightNow tracking failed: " . ($response['error'] ?? 'Unknown error')
            );
        }

        $data = $response['data'];

        return [
            'tracking_number' => $data['tracking_number'],
            'status' => $this->mapStatus($data['status']),
            'estimated_delivery' => $data['estimated_delivery'] ?? null,
            'delivered_at' => $data['delivered_at'] ?? null,
            'events' => $data['events'] ?? [],
            'current_location' => $data['current_location'] ?? null,
        ];
    }

    /**
     * @inheritDoc
     */
    public function cancelBooking(string $bookingReference): array
    {
        $response = $this->makeRequest('DELETE', "/bookings/{$bookingReference}");

        if (!$response['success']) {
            throw new \RuntimeException(
                "FreightNow cancellation failed: " . ($response['error'] ?? 'Unknown error')
            );
        }

        $this->logger->info('FreightNow booking cancelled', [
            'booking_reference' => $bookingReference,
        ]);

        return [
            'success' => true,
            'booking_reference' => $bookingReference,
            'cancelled_at' => $response['data']['cancelled_at'] ?? date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getLabel(string $bookingReference): string
    {
        $response = $this->makeRequest('GET', "/bookings/{$bookingReference}/label");

        if (!$response['success']) {
            throw new \RuntimeException(
                "FreightNow label retrieval failed: " . ($response['error'] ?? 'Unknown error')
            );
        }

        // Return base64 encoded PDF
        return $response['data']['label_pdf'] ?? '';
    }

    /**
     * @inheritDoc
     */
    public function validateAddress(array $address): array
    {
        $response = $this->makeRequest('POST', '/address/validate', $address);

        if (!$response['success']) {
            return [
                'valid' => false,
                'errors' => [$response['error'] ?? 'Validation failed'],
            ];
        }

        return [
            'valid' => $response['data']['valid'],
            'errors' => $response['data']['errors'] ?? [],
            'suggestions' => $response['data']['suggestions'] ?? [],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getProviderName(): string
    {
        return 'freight_now';
    }

    // ========================================================================
    // Private Helper Methods
    // ========================================================================

    /**
     * Build booking payload for FreightNow API
     *
     * @param array $bookingData Input data
     * @return array API-formatted payload
     */
    private function buildBookingPayload(array $bookingData): array
    {
        return [
            'from' => [
                'name' => $bookingData['from_address']['name'],
                'company' => $bookingData['from_address']['company'] ?? '',
                'address1' => $bookingData['from_address']['address1'],
                'address2' => $bookingData['from_address']['address2'] ?? '',
                'suburb' => $bookingData['from_address']['suburb'],
                'postcode' => $bookingData['from_address']['postcode'],
                'state' => $bookingData['from_address']['state'],
                'country' => $bookingData['from_address']['country'] ?? 'AU',
                'phone' => $bookingData['from_address']['phone'] ?? '',
                'email' => $bookingData['from_address']['email'] ?? '',
            ],
            'to' => [
                'name' => $bookingData['to_address']['name'],
                'company' => $bookingData['to_address']['company'] ?? '',
                'address1' => $bookingData['to_address']['address1'],
                'address2' => $bookingData['to_address']['address2'] ?? '',
                'suburb' => $bookingData['to_address']['suburb'],
                'postcode' => $bookingData['to_address']['postcode'],
                'state' => $bookingData['to_address']['state'],
                'country' => $bookingData['to_address']['country'] ?? 'AU',
                'phone' => $bookingData['to_address']['phone'] ?? '',
                'email' => $bookingData['to_address']['email'] ?? '',
            ],
            'items' => array_map(function ($item) {
                return [
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'weight' => $item['weight'],
                    'length' => $item['length'] ?? null,
                    'width' => $item['width'] ?? null,
                    'height' => $item['height'] ?? null,
                ];
            }, $bookingData['items']),
            'service' => $bookingData['service'] ?? 'STANDARD',
            'reference' => $bookingData['reference'] ?? '',
            'instructions' => $bookingData['instructions'] ?? '',
        ];
    }

    /**
     * Make HTTP request to FreightNow API
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array|null $data Request payload
     * @return array Response data
     */
    private function makeRequest(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->apiUrl . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        if ($method === 'POST' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logger->error('FreightNow API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $error,
            ]);

            return [
                'success' => false,
                'error' => $error,
            ];
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $this->logger->error('FreightNow API returned error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'response' => $response,
            ]);

            return [
                'success' => false,
                'error' => $data['message'] ?? "HTTP {$httpCode} error",
            ];
        }

        return [
            'success' => true,
            'data' => $data,
        ];
    }

    /**
     * Map FreightNow status to standard status
     *
     * @param string $freightNowStatus Provider-specific status
     * @return string Standardized status
     */
    private function mapStatus(string $freightNowStatus): string
    {
        $statusMap = [
            'PENDING' => 'PENDING',
            'COLLECTED' => 'IN_TRANSIT',
            'IN_TRANSIT' => 'IN_TRANSIT',
            'OUT_FOR_DELIVERY' => 'OUT_FOR_DELIVERY',
            'DELIVERED' => 'DELIVERED',
            'FAILED_DELIVERY' => 'FAILED',
            'RETURNED' => 'RETURNED',
            'CANCELLED' => 'CANCELLED',
        ];

        return $statusMap[$freightNowStatus] ?? 'UNKNOWN';
    }
}
