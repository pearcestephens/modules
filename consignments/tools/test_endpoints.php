<?php
declare(strict_types=1);

/**
 * tools/test_endpoints.php
 * CLI curl smoke tester for Consignments module public endpoints.
 * Usage: php tools/test_endpoints.php --base="https://staff.vapeshed.co.nz" --transfer=13219
 *
 * @author AI
 * @last-modified 2025-10-12
 * @dependencies PHP cURL extension
 */

$shortOpts = '';
$longOpts = [
    'base:',
    'transfer::',
    'timeout::',
    'user::',
    'pass::',
];

$options = getopt($shortOpts, $longOpts);

$base = isset($options['base']) ? rtrim((string)$options['base'], '/') : '';
if ($base === '') {
    fwrite(STDERR, "Missing --base URL. Example: php tools/test_endpoints.php --base=\"https://staff.vapeshed.co.nz\"\n");
    exit(1);
}

$transferId = isset($options['transfer']) ? (int)$options['transfer'] : 0;
if ($transferId <= 0) {
    $transferId = 1;
}

$timeout = isset($options['timeout']) ? (float)$options['timeout'] : 10.0;
if ($timeout <= 0) {
    $timeout = 10.0;
}

$httpAuthUser = isset($options['user']) ? (string)$options['user'] : '';
$httpAuthPass = isset($options['pass']) ? (string)$options['pass'] : '';
$useAuth = ($httpAuthUser !== '' || $httpAuthPass !== '');

$endpoints = [
    'health' => [
        'url' => $base . '/modules/consignments/health.php',
        'headers' => ['Accept: application/json'],
        'expectJson' => true,
    ],
    'home' => [
        'url' => $base . '/modules/consignments/?bot=true',
        'headers' => [],
        'expectJson' => false,
    ],
    'pack' => [
        'url' => $base . '/modules/consignments/transfers/pack?bot=true&transfer=' . $transferId,
        'headers' => [],
        'expectJson' => false,
    ],
];

if (!extension_loaded('curl')) {
    fwrite(STDERR, "The PHP cURL extension is required.\n");
    exit(2);
}

$results = [];
foreach ($endpoints as $key => $meta) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $meta['url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $meta['headers'],
        CURLOPT_USERAGENT => 'ConsignmentsEndpointTester/1.0',
    ]);

    if ($useAuth) {
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $httpAuthUser . ':' . $httpAuthPass);
    }

    $execStart = microtime(true);
    $response = curl_exec($ch);
    $execTime = microtime(true) - $execStart;

    if ($response === false) {
        $results[$key] = [
            'ok' => false,
            'error' => curl_error($ch),
        ];
        curl_close($ch);
        continue;
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $rawHeaders = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    curl_close($ch);

    $summary = [
        'ok' => ($statusCode >= 200 && $statusCode < 400),
        'status' => $statusCode,
        'time_ms' => (int)round($execTime * 1000),
        'headers' => $rawHeaders,
        'bodyPreview' => substr($body, 0, 500),
    ];

    if ($meta['expectJson']) {
        $json = json_decode($body, true);
        $summary['json'] = $json;
        $summary['jsonError'] = json_last_error_msg();
        $summary['ok'] = $summary['ok'] && is_array($json);
    }

    $results[$key] = $summary;
}

foreach ($results as $name => $data) {
    echo str_repeat('=', 72) . PHP_EOL;
    echo strtoupper($name) . PHP_EOL;
    echo 'URL: ' . $endpoints[$name]['url'] . PHP_EOL;

    if (!($data['ok'] ?? false)) {
        echo 'RESULT: FAIL' . PHP_EOL;
        echo 'DETAILS: ' . ($data['error'] ?? ('HTTP ' . ($data['status'] ?? 'unknown'))) . PHP_EOL;
        continue;
    }

    echo 'RESULT: OK' . PHP_EOL;
    echo 'STATUS: ' . $data['status'] . PHP_EOL;
    echo 'TIME: ' . $data['time_ms'] . " ms" . PHP_EOL;

    if (isset($data['json'])) {
        echo 'JSON: ' . json_encode($data['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        echo 'BODY (first 500 bytes):' . PHP_EOL;
        echo $data['bodyPreview'] . PHP_EOL;
    }
}

exit(0);
