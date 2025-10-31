<?php
declare(strict_types=1);

namespace CIS\Modules\StaffAccounts;

/**
 * Snapshot Service
 * 
 * Handles payroll snapshot analysis, failed payment detection,
 * and payment history tracking
 * 
 * @package CIS\Modules\StaffAccounts
 * @version 2.0.0
 */
class SnapshotService
{
    private static string $snapshotDir = '/assets/functions/xeroAPI/_payroll_snapshots';
    
    /**
     * Scan all available snapshot files
     * 
     * @return array<int, array<string, mixed>>
     */
    public static function scanAllSnapshots(): array
    {
        $snapshots = [];
        $snapshotDir = $_SERVER['DOCUMENT_ROOT'] . self::$snapshotDir;
        $files = glob($snapshotDir . '/snapshot_*.summary.json');
        
        if (!$files) {
            return [];
        }
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $snapshots[] = [
                    'run_id' => $data['run_id'],
                    'started' => $data['started'],
                    'ended' => $data['ended'] ?? null,
                    'meta' => $data['meta'] ?? [],
                    'totals' => $data['totals'] ?? [],
                    'users' => $data['users'] ?? [],
                    'file_path' => $file
                ];
            }
        }
        
        // Sort by date descending
        usort($snapshots, fn($a, $b) => strcmp($b['started'], $a['started']));
        
        return $snapshots;
    }
    
    /**
     * Get failed payments summary from snapshots
     * 
     * Analyzes last 3 weeks of snapshots and identifies:
     * - Planned deductions that were never allocated
     * - Groups by user with weekly failures
     * 
     * @return array<int, array<string, mixed>>
     */
    public static function getFailedPaymentsSummary(): array
    {
        $snapshots = self::scanAllSnapshots();
        $failedSummary = [];
        
        // Look at last 3 weeks of snapshots
        $cutoff = date('Y-m-d H:i:s', strtotime('-3 weeks'));
        
        // Group snapshots by user and week
        $weeklyFailures = [];
        
        foreach ($snapshots as $snapshot) {
            if ($snapshot['started'] < $cutoff) continue;
            
            // Get week number (Year-Week format)
            $weekKey = date('Y-W', strtotime($snapshot['started']));
            
            foreach ($snapshot['users'] as $user) {
                $userId = $user['user_id'];
                
                // If planned deduction but no allocations = failed
                if (($user['planned_deduction'] ?? 0) > 0 && ($user['allocations'] ?? 0) === 0) {
                    $userWeekKey = $userId . '_' . $weekKey;
                    
                    // Only count the latest failure per week per user
                    if (!isset($weeklyFailures[$userWeekKey]) || 
                        $snapshot['started'] > $weeklyFailures[$userWeekKey]['date']) {
                        
                        $weeklyFailures[$userWeekKey] = [
                            'user_id' => $userId,
                            'name' => $user['name'],
                            'email' => $user['email'],
                            'vend_customer_id' => $user['vend_customer'],
                            'amount' => $user['planned_deduction'],
                            'date' => $snapshot['started'],
                            'run_id' => $snapshot['run_id'],
                            'week' => $weekKey,
                            'current_balance' => $user['final_balance'] ?? $user['initial_balance']
                        ];
                    }
                }
            }
        }
        
        // Aggregate by user
        foreach ($weeklyFailures as $failure) {
            $userId = $failure['user_id'];
            
            if (!isset($failedSummary[$userId])) {
                $failedSummary[$userId] = [
                    'user_id' => $userId,
                    'name' => $failure['name'],
                    'email' => $failure['email'],
                    'vend_customer_id' => $failure['vend_customer_id'],
                    'total_failed_amount' => 0,
                    'failed_runs' => 0,
                    'planned_deductions' => [],
                    'current_balance' => $failure['current_balance']
                ];
            }
            
            // Add this weekly failure
            $failedSummary[$userId]['total_failed_amount'] += $failure['amount'];
            $failedSummary[$userId]['failed_runs']++;
            $failedSummary[$userId]['planned_deductions'][] = [
                'run_id' => $failure['run_id'],
                'date' => $failure['date'],
                'amount' => $failure['amount'],
                'week' => $failure['week']
            ];
        }
        
        // Sort by total failed amount descending
        uasort($failedSummary, fn($a, $b) => $b['total_failed_amount'] <=> $a['total_failed_amount']);
        
        return array_values($failedSummary);
    }
    
    /**
     * Get payment history for a specific customer
     * 
     * @param string $vendCustomerId Vend customer ID
     * @param int $limit Maximum number of events
     * @return array<int, array<string, mixed>>
     */
    public static function getPaymentHistory(string $vendCustomerId, int $limit = 100): array
    {
        $history = [];
        $snapshotDir = $_SERVER['DOCUMENT_ROOT'] . self::$snapshotDir;
        $eventFiles = glob($snapshotDir . '/snapshot_*.events.jsonl');
        
        if (!$eventFiles) {
            return [];
        }
        
        sort($eventFiles, SORT_STRING);
        
        foreach (array_reverse($eventFiles) as $file) {
            if (count($history) >= $limit) break;
            
            $handle = fopen($file, 'r');
            if (!$handle) continue;
            
            while (($line = fgets($handle)) !== false) {
                if (count($history) >= $limit) break;
                
                $event = json_decode(trim($line), true);
                if (!$event) continue;
                
                if (isset($event['vend_customer']) && $event['vend_customer'] === $vendCustomerId) {
                    $history[] = $event;
                }
            }
            
            fclose($handle);
        }
        
        return array_reverse($history);
    }
}
