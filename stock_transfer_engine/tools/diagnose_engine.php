<?php
declare(strict_types=1);

// Stock Transfer Engine – Local Diagnostics
// Purpose: Quickly show what parts of the engine/pipeline are present vs missing
// Safe: Read-only filesystem checks; no DB access; no side effects

header('Content-Type: application/json');

$root = realpath(__DIR__ . '/..');
$cronRoot = realpath($root . '/../../assets/cron/automatic_stock_transfers');

function file_status(?string $path): array {
    if (!$path) {
        return ['exists' => false, 'size' => 0, 'readable' => false, 'path' => null];
    }
    $exists = file_exists($path);
    return [
        'exists' => $exists,
        'size' => $exists ? (int) filesize($path) : 0,
        'readable' => $exists ? is_readable($path) : false,
        'path' => $path,
    ];
}

$engineFiles = [
    'module_services' => [
        'VendTransferAPI.php' => file_status($root . '/services/VendTransferAPI.php'),
        'ExcessDetectionEngine.php' => file_status($root . '/services/ExcessDetectionEngine.php'),
        'WarehouseManager.php' => file_status($root . '/services/WarehouseManager.php'),
    ],
    'module_config' => [
        'config/database.php' => file_status($root . '/config/database.php'),
        'config/warehouses.php' => file_status($root . '/config/warehouses.php'),
    ],
    'module_schema' => [
        'database/stock_transfer_engine_schema.sql' => file_status($root . '/database/stock_transfer_engine_schema.sql'),
        'database/current_database_schema.sql' => file_status($root . '/database/current_database_schema.sql'),
        'database/migration_addon.sql' => file_status($root . '/database/migration_addon.sql'),
    ],
];

$cronFiles = [
    'entrypoints' => [
        'run.php' => file_status($cronRoot ? ($cronRoot . '/run.php') : null),
        'automatic-stock-transfers.php (legacy)' => file_status($root . '/../../assets/cron/automatic-stock-transfers.php'),
        'automatic-stock-transfers_v2.php (legacy)' => file_status($root . '/../../assets/cron/automatic-stock-transfers_v2.php'),
    ],
    'services' => [
        'src/Services/TransferOrchestrator.php' => file_status($cronRoot ? ($cronRoot . '/src/Services/TransferOrchestrator.php') : null),
        'src/Services/ConfigService.php' => file_status($cronRoot ? ($cronRoot . '/src/Services/ConfigService.php') : null),
        'src/Services/LoggingService.php' => file_status($cronRoot ? ($cronRoot . '/src/Services/LoggingService.php') : null),
        'src/Services/EventLoggingService.php' => file_status($cronRoot ? ($cronRoot . '/src/Services/EventLoggingService.php') : null),
        'src/Services/ProductService.php' => file_status($cronRoot ? ($cronRoot . '/src/Services/ProductService.php') : null),
        'src/Services/ProductTraceService.php' => file_status($cronRoot ? ($cronRoot . '/src/Services/ProductTraceService.php') : null),
        'src/Services/DemandCalculator.php' => file_status($cronRoot ? ($cronRoot . '/src/Services/DemandCalculator.php') : null),
        'src/Services/AllocationService.php' => file_status($cronRoot ? ($cronRoot . '/src/Services/AllocationService.php') : null),
        'src/Services/WarehouseBufferService.php' => file_status($cronRoot ? ($cronRoot . '/src/Services/WarehouseBufferService.php') : null),
        'src/Services/TransferExecutionService.php' => file_status($cronRoot ? ($cronRoot . '/src/Services/TransferExecutionService.php') : null),
        'src/Services/EnforcementPostProcessor.php' => file_status($cronRoot ? ($cronRoot . '/src/Services/EnforcementPostProcessor.php') : null),
        'src/Services/ValidationService.php' => file_status($cronRoot ? ($cronRoot . '/src/Services/ValidationService.php') : null),
        'src/Services/SmartAutoModeExecutor.php' => file_status($cronRoot ? ($cronRoot . '/src/Services/SmartAutoModeExecutor.php') : null),
        'src/Services/AllOutletsExecutor.php' => file_status($cronRoot ? ($cronRoot . '/src/Services/AllOutletsExecutor.php') : null),
    ],
    'ui' => [
        'dashboard/index.php' => file_status($cronRoot ? ($cronRoot . '/dashboard/index.php') : null),
        'ui/mega_command_center.html' => file_status($cronRoot ? ($cronRoot . '/ui/mega_command_center.html') : null),
    ],
];

$summary = function(array $group): array {
    $total = 0; $present = 0; $nonEmpty = 0;
    foreach ($group as $name => $info) {
        $total++;
        if ($info['exists']) {
            $present++;
            if ($info['size'] > 0) { $nonEmpty++; }
        }
    }
    return [
        'total' => $total,
        'present' => $present,
        'non_empty' => $nonEmpty,
    ];
};

$report = [
    'engine_module' => [
        'services' => [
            'files' => $engineFiles['module_services'],
            'summary' => $summary($engineFiles['module_services']),
        ],
        'config' => [
            'files' => $engineFiles['module_config'],
            'summary' => $summary($engineFiles['module_config']),
        ],
        'schema' => [
            'files' => $engineFiles['module_schema'],
            'summary' => $summary($engineFiles['module_schema']),
        ],
    ],
    'automatic_stock_transfers_cron' => [
        'entrypoints' => [
            'files' => $cronFiles['entrypoints'],
            'summary' => $summary($cronFiles['entrypoints']),
        ],
        'services' => [
            'files' => $cronFiles['services'],
            'summary' => $summary($cronFiles['services']),
        ],
        'ui' => [
            'files' => $cronFiles['ui'],
            'summary' => $summary($cronFiles['ui']),
        ],
    ],
    'notes' => [
        'run_php_includes_many_missing' => true,
        'impact' => 'automatic_stock_transfers/run.php will error until missing services are restored',
        'safe_next_steps' => [
            'Use TransferManager UI for manual transfers meanwhile',
            'Run read-only analytics pages (e.g., stock-transfers views) to review imbalances',
            'Restore or rebuild minimal orchestrator to generate suggestions only (no apply)'
        ],
    ],
];

echo json_encode($report, JSON_PRETTY_PRINT);
