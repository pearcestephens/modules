<?php
declare(strict_types=1);

/**
 * Lightspeed (Vend) API Client — hardened
 * - Bearer token auth
 * - Retries on 429/5xx with backoff
 * - Optional Idempotency-Key and Request-ID headers
 */

final class LightspeedClient
{
    private string $baseUrl;
    private string $token;
    private int $timeout = 30;
    private int $connectTimeout = 10;
    private int $maxRetries = 2;

    public function __construct(string $baseUrl, string $token)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
        if ($this->token === '') {
            throw new RuntimeException('Lightspeed API token is missing');
        }
    }

    public function createConsignment(string $sourceOutletId, string $destinationOutletId, string $reference): array
    {
        return $this->request('POST', '/consignments', [
            'type' => 'OUTLET',
            'status' => 'OPEN',
            'source_outlet_id' => $sourceOutletId,
            'destination_outlet_id' => $destinationOutletId,
            'reference' => $reference,
        ]);
    }

    public function addConsignmentProduct(string $consignmentId, string $vendProductId, int $count): array
    {
        return $this->request('POST', '/consignment_products', [
            'consignment_id' => $consignmentId,
            'product_id' => $vendProductId,
            'count' => $count,
        ]);
    }

    public function updateConsignmentStatus(string $consignmentId, string $status): array
    {
        return $this->request('PATCH', '/consignments/' . rawurlencode($consignmentId), [
            'status' => $status
        ]);
    }

    /**
     * Low-level request with retries and structured return.
     * @return array{ok:bool,status:int,headers:array,json:mixed,raw:string,error:?string}
     */
    public function request(string $method, string $path, ?array $payload = null, array $extraHeaders = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $method = strtoupper($method);
        $attempt = 0;
        $last = ['ok' => false, 'status' => 0, 'headers' => [], 'json' => null, 'raw' => '', 'error' => null];

        $reqId = 'ls_' . bin2hex(random_bytes(8));
        $idemp = hash('sha256', $url . '|' . $reqId);

        $baseHeaders = [
            'Authorization: Bearer ' . $this->token, // ✅ correct Bearer
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: CIS Consignments/2.1 (+staff.vapeshed.co.nz)',
            'X-Request-ID: ' . $reqId,
            'Idempotency-Key: ' . $idemp
        ];
        $headers = array_merge($baseHeaders, $extraHeaders);

        do {
            $attempt++;
            $ch = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers
            ];
            if ($payload !== null) {
                $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
            }
            curl_setopt_array($ch, $opts);

            $response = curl_exec($ch);
            $errno = curl_errno($ch);
            $err = $errno ? curl_error($ch) : null;
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            $rawHeaders = substr((string)$response, 0, $headerSize);
            $body = substr((string)$response, $headerSize);
            $headersAssoc = $this->parseHeaders($rawHeaders);
            $json = $body !== '' ? (json_decode($body, true) ?? null) : null;

            $last = [
                'ok' => ($status >= 200 && $status < 300) && !$err,
                'status' => $status,
                'headers' => $headersAssoc,
                'json' => $json,
                'raw' => (string)$body,
                'error' => $err
            ];

            // Retry on 429/5xx or curl error
            if ($last['ok']) {
                return $last;
            }
            if ($status === 429 || ($status >= 500 && $status <= 599) || $err) {
                $wait = $this->computeBackoff($attempt, $headersAssoc);
                usleep($wait * 1000000);
                continue;
            }
            return $last;
        } while ($attempt <= $this->maxRetries);

        return $last;
    }

    private function computeBackoff(int $attempt, array $headers): int
    {
        if (isset($headers['retry-after'])) {
            $v = (int)$headers['retry-after'];
            if ($v > 0 && $v <= 30) return $v;
        }
        return min(8, 1 << ($attempt - 1)); // 1,2,4,8
    }

    /** @return array<string,string> */
    private function parseHeaders(string $raw): array
    {
        $out = [];
        foreach (explode("\n", $raw) as $line) {
            $p = strpos($line, ':');
            if ($p !== false) {
                $k = strtolower(trim(substr($line, 0, $p)));
                $v = trim(substr($line, $p + 1));
                if ($k !== '') $out[$k] = $v;
            }
        }
        return $out;
    }
}
