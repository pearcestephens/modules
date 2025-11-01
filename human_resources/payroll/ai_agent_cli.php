#!/usr/bin/env php
<?php
/**
 * AI Agent CLI - Run autonomous monitoring and self-healing
 *
 * Usage:
 *   php ai_agent_cli.php run          Run single cycle
 *   php ai_agent_cli.php monitor      Continuous monitoring (daemon)
 *   php ai_agent_cli.php status       Show agent status
 *   php ai_agent_cli.php health       Run health checks only
 */

declare(strict_types=1);

require_once __DIR__ . '/ai/AgentEngine.php';

use HumanResources\Payroll\AI\AgentEngine;

// Parse command
$command = $argv[1] ?? 'run';

// Create agent
$agent = new AgentEngine([
    'auto_heal' => true,
    'auto_sync' => false, // Set to true for production
    'auto_reconcile' => false, // Set to true for production
]);

switch ($command) {
    case 'run':
        echo "🤖 Running AI Agent cycle...\n\n";
        $results = $agent->runCycle();
        displayResults($results);
        break;

    case 'monitor':
        echo "🤖 Starting AI Agent in monitor mode (Ctrl+C to stop)...\n\n";
        $cycles = 0;
        while (true) {
            $cycles++;
            echo "\n" . str_repeat("=", 70) . "\n";
            echo "Cycle #$cycles - " . date('Y-m-d H:i:s') . "\n";
            echo str_repeat("=", 70) . "\n\n";

            $results = $agent->runCycle();
            displayResults($results);

            echo "\nSleeping for 60 seconds...\n";
            sleep(60);
        }
        break;

    case 'status':
        echo "🤖 AI Agent Status\n\n";
        $results = $agent->runCycle();
        echo "Agent is operational\n";
        echo "Last check: " . $results['timestamp'] . "\n";
        break;

    case 'health':
        echo "🏥 Running Health Checks...\n\n";
        $results = $agent->runCycle();
        displayHealthChecks($results['checks']);
        break;

    default:
        echo "Unknown command: $command\n";
        echo "Usage: php ai_agent_cli.php {run|monitor|status|health}\n";
        exit(1);
}

function displayResults(array $results): void
{
    echo "Timestamp: {$results['timestamp']}\n\n";

    echo "Health Checks:\n";
    displayHealthChecks($results['checks']);

    if (!empty($results['actions'])) {
        echo "\nActions Taken:\n";
        foreach ($results['actions'] as $name => $action) {
            echo "  - $name: " . json_encode($action) . "\n";
        }
    }

    if (!empty($results['errors'])) {
        echo "\n⚠️  Errors:\n";
        foreach ($results['errors'] as $error) {
            echo "  - $error\n";
        }
    }
}

function displayHealthChecks(array $checks): void
{
    foreach ($checks as $name => $check) {
        $status = $check['status'] ?? 'unknown';
        $icon = getStatusIcon($status);
        echo "  $icon $name: $status\n";

        if (is_array($check)) {
            foreach ($check as $key => $value) {
                if ($key !== 'status' && !is_array($value)) {
                    echo "      $key: $value\n";
                }
            }
        }
    }
}

function getStatusIcon(string $status): string
{
    return match($status) {
        'healthy' => '✅',
        'degraded' => '⚠️',
        'error' => '❌',
        'not_configured' => '⚙️',
        'backlog' => '📦',
        'low' => '⚠️',
        default => '❓'
    };
}
