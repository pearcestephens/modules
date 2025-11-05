<?php
/**
 * Barcode Analytics & Fraud Detection API
 *
 * Real-time fraud detection, performance tracking, and analytics
 */

require_once '../../../config/database.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? null;

switch ($action) {
    case 'log_scan':
        logScan();
        break;

    case 'start_session':
        startReceivingSession();
        break;

    case 'update_session':
        updateReceivingSession();
        break;

    case 'complete_session':
        completeReceivingSession();
        break;

    case 'get_performance':
        getPerformance();
        break;

    case 'get_leaderboard':
        getLeaderboard();
        break;

    case 'check_achievements':
        checkAchievements();
        break;

    case 'get_suspicious_scans':
        getSuspiciousScans();
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Log a barcode scan with fraud detection
 */
function logScan() {
    global $conn;

    $data = json_decode(file_get_contents('php://input'), true);

    $transferId = $data['transfer_id'] ?? null;
    $transferType = $data['transfer_type'] ?? 'stock_transfer';
    $userId = $data['user_id'] ?? null;
    $outletId = $data['outlet_id'] ?? null;
    $sessionId = $data['session_id'] ?? session_id();
    $barcode = $data['barcode'] ?? null;
    $productId = $data['product_id'] ?? null;
    $expectedProductId = $data['expected_product_id'] ?? null;
    $scanResult = $data['scan_result'] ?? 'success';
    $deviceType = $data['device_type'] ?? 'usb_scanner';

    if (!$barcode || !$userId) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }

    // Get time since last scan for this session
    $stmt = $conn->prepare("
        SELECT TIMESTAMPDIFF(MICROSECOND, scanned_at, NOW())/1000 as ms_since_last
        FROM BARCODE_SCAN_EVENTS
        WHERE session_id = ?
        ORDER BY scanned_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('s', $sessionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $timeSinceLastMs = $result->num_rows > 0 ? $result->fetch_assoc()['ms_since_last'] : null;

    // Run fraud detection
    $fraudCheck = detectFraud($barcode, $timeSinceLastMs, $scanResult, $sessionId);

    // Log the scan
    $stmt = $conn->prepare("
        INSERT INTO BARCODE_SCAN_EVENTS
        (transfer_id, transfer_type, user_id, outlet_id, session_id, barcode, product_id,
         expected_product_id, scan_result, time_since_last_scan_ms, is_suspicious,
         fraud_score, fraud_reasons, device_type, user_agent, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $fraudReasons = json_encode($fraudCheck['reasons']);
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt->bind_param('isiisisssiisssss',
        $transferId, $transferType, $userId, $outletId, $sessionId, $barcode,
        $productId, $expectedProductId, $scanResult, $timeSinceLastMs,
        $fraudCheck['is_suspicious'], $fraudCheck['fraud_score'], $fraudReasons,
        $deviceType, $userAgent, $ipAddress
    );

    if ($stmt->execute()) {
        $eventId = $conn->insert_id;

        // Update receiving session stats
        if ($transferId) {
            $conn->query("
                UPDATE RECEIVING_SESSIONS
                SET items_scanned = items_scanned + 1,
                    error_count = error_count + " . ($scanResult !== 'success' ? 1 : 0) . ",
                    duplicate_scan_count = duplicate_scan_count + " . ($scanResult === 'duplicate' ? 1 : 0) . ",
                    wrong_product_count = wrong_product_count + " . ($scanResult === 'wrong_product' ? 1 : 0) . "
                WHERE transfer_id = $transferId
            ");
        }

        echo json_encode([
            'success' => true,
            'event_id' => $eventId,
            'fraud_check' => $fraudCheck,
            'time_since_last_ms' => $timeSinceLastMs
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to log scan']);
    }
}

/**
 * Detect fraud in a barcode scan
 */
function detectFraud($barcode, $timeSinceLastMs, $scanResult, $sessionId) {
    global $conn;

    $isSuspicious = false;
    $fraudScore = 0;
    $reasons = [];

    // Get active fraud rules
    $rules = $conn->query("SELECT * FROM FRAUD_DETECTION_RULES WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);

    foreach ($rules as $rule) {
        $config = json_decode($rule['rule_config'], true);
        $triggered = false;

        switch ($rule['rule_type']) {
            case 'invalid_barcode':
                foreach ($config['patterns'] as $pattern) {
                    if (preg_match('/' . $pattern . '/', $barcode)) {
                        $triggered = true;
                        $reasons[] = $rule['rule_name'] . ': Matches pattern ' . $pattern;
                        break;
                    }
                }
                break;

            case 'timing_anomaly':
                if ($timeSinceLastMs !== null && $timeSinceLastMs < $config['min_ms_between_scans']) {
                    $triggered = true;
                    $reasons[] = $rule['rule_name'] . ': Too fast (' . $timeSinceLastMs . 'ms)';
                }
                break;

            case 'duplicate':
                // Check for recent duplicates
                $stmt = $GLOBALS['conn']->prepare("
                    SELECT COUNT(*) as count
                    FROM BARCODE_SCAN_EVENTS
                    WHERE session_id = ?
                    AND barcode = ?
                    AND scanned_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
                ");
                $stmt->bind_param('ssi', $sessionId, $barcode, $config['window_seconds']);
                $stmt->execute();
                $dupCount = $stmt->get_result()->fetch_assoc()['count'];

                if ($dupCount >= $config['max_duplicates']) {
                    $triggered = true;
                    $reasons[] = $rule['rule_name'] . ': ' . $dupCount . ' duplicates in ' . $config['window_seconds'] . 's';
                }
                break;

            case 'pattern':
                foreach ($config['suspicious_sequences'] as $seq) {
                    if (strpos($barcode, $seq) !== false) {
                        $triggered = true;
                        $reasons[] = $rule['rule_name'] . ': Contains sequence ' . $seq;
                        break;
                    }
                }
                break;
        }

        if ($triggered) {
            $isSuspicious = true;
            $fraudScore += $rule['fraud_points'];
        }
    }

    // Cap fraud score at 100
    $fraudScore = min($fraudScore, 100);

    return [
        'is_suspicious' => $isSuspicious,
        'fraud_score' => $fraudScore,
        'reasons' => $reasons
    ];
}

/**
 * Start a receiving session
 */
function startReceivingSession() {
    global $conn;

    $data = json_decode(file_get_contents('php://input'), true);

    $transferId = $data['transfer_id'] ?? null;
    $transferType = $data['transfer_type'] ?? 'stock_transfer';
    $userId = $data['user_id'] ?? null;
    $outletId = $data['outlet_id'] ?? null;
    $totalItems = $data['total_items'] ?? 0;
    $totalQuantity = $data['total_quantity_expected'] ?? 0;

    // Photo requirements
    $requiresInvoice = $data['requires_invoice_photo'] ?? false;
    $requiresPackingSlip = $data['requires_packing_slip_photo'] ?? true; // Usually required
    $requiresReceipt = $data['requires_receipt_photo'] ?? false;
    $requiresDamage = $data['requires_damage_photos'] ?? false;

    if (!$transferId || !$userId) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }

    // Check if session already exists
    $existing = $conn->query("SELECT session_id FROM RECEIVING_SESSIONS WHERE transfer_id = $transferId")->fetch_assoc();

    if ($existing) {
        echo json_encode([
            'success' => true,
            'session_id' => $existing['session_id'],
            'message' => 'Session already exists'
        ]);
        return;
    }

    // Create new session
    $stmt = $conn->prepare("
        INSERT INTO RECEIVING_SESSIONS
        (transfer_id, transfer_type, user_id, outlet_id, total_items, total_quantity_expected,
         requires_invoice_photo, requires_packing_slip_photo, requires_receipt_photo, requires_damage_photos)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param('isiiiiiiii',
        $transferId, $transferType, $userId, $outletId, $totalItems, $totalQuantity,
        $requiresInvoice, $requiresPackingSlip, $requiresReceipt, $requiresDamage
    );

    if ($stmt->execute()) {
        $sessionId = $conn->insert_id;

        // Create required photos record
        $conn->query("
            INSERT INTO RECEIVING_REQUIRED_PHOTOS
            (transfer_id, transfer_type, requires_invoice, requires_packing_slip, requires_receipt, requires_damage_photos)
            VALUES ($transferId, '$transferType', $requiresInvoice, $requiresPackingSlip, $requiresReceipt, $requiresDamage)
        ");

        echo json_encode([
            'success' => true,
            'session_id' => $sessionId,
            'message' => 'Receiving session started'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to create session']);
    }
}

/**
 * Update receiving session (during receiving)
 */
function updateReceivingSession() {
    global $conn;

    $data = json_decode(file_get_contents('php://input'), true);

    $sessionId = $data['session_id'] ?? null;
    $quantityReceived = $data['quantity_received'] ?? null;
    $quantityDamaged = $data['quantity_damaged'] ?? null;

    if (!$sessionId) {
        echo json_encode(['success' => false, 'error' => 'Session ID required']);
        return;
    }

    $updates = [];
    if ($quantityReceived !== null) $updates[] = "total_quantity_received = $quantityReceived";
    if ($quantityDamaged !== null) $updates[] = "total_quantity_damaged = $quantityDamaged";

    if (empty($updates)) {
        echo json_encode(['success' => false, 'error' => 'Nothing to update']);
        return;
    }

    $sql = "UPDATE RECEIVING_SESSIONS SET " . implode(', ', $updates) . " WHERE session_id = $sessionId";

    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Session updated']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Update failed']);
    }
}

/**
 * Complete receiving session (calculate final stats)
 */
function completeReceivingSession() {
    global $conn;

    $data = json_decode(file_get_contents('php://input'), true);

    $sessionId = $data['session_id'] ?? null;
    $completionType = $data['completion_type'] ?? 'full'; // 'full' or 'partial'

    if (!$sessionId) {
        echo json_encode(['success' => false, 'error' => 'Session ID required']);
        return;
    }

    // Get session data
    $session = $conn->query("SELECT * FROM RECEIVING_SESSIONS WHERE session_id = $sessionId")->fetch_assoc();

    if (!$session) {
        echo json_encode(['success' => false, 'error' => 'Session not found']);
        return;
    }

    // Calculate duration
    $duration = strtotime('now') - strtotime($session['started_at']);

    // Calculate scans per minute
    $scansPerMinute = $duration > 0 ? ($session['items_scanned'] / ($duration / 60)) : 0;

    // Calculate accuracy (95% is target)
    $totalExpected = $session['total_quantity_expected'];
    $totalReceived = $session['total_quantity_received'];
    $errorCount = $session['error_count'];

    $accuracyPercentage = 100;
    if ($totalExpected > 0) {
        $variance = abs($totalExpected - $totalReceived);
        $accuracyPercentage = max(0, 100 - (($variance / $totalExpected) * 100) - ($errorCount * 2));
    }

    // Calculate performance score (0-100)
    $performanceScore = calculatePerformanceScore($scansPerMinute, $accuracyPercentage, $errorCount);

    // Update session
    $stmt = $conn->prepare("
        UPDATE RECEIVING_SESSIONS
        SET completed_at = NOW(),
            duration_seconds = ?,
            scans_per_minute = ?,
            accuracy_percentage = ?,
            performance_score = ?,
            status = 'completed',
            completion_type = ?
        WHERE session_id = ?
    ");

    $stmt->bind_param('iddiis',
        $duration, $scansPerMinute, $accuracyPercentage, $performanceScore, $completionType, $sessionId
    );

    if ($stmt->execute()) {
        // Update daily staff performance
        updateDailyPerformance($session['user_id'], $session['outlet_id']);

        // Check for new achievements
        $newAchievements = checkForAchievements($session['user_id']);

        echo json_encode([
            'success' => true,
            'session_id' => $sessionId,
            'performance' => [
                'duration_seconds' => $duration,
                'scans_per_minute' => round($scansPerMinute, 2),
                'accuracy_percentage' => round($accuracyPercentage, 2),
                'performance_score' => $performanceScore,
                'new_achievements' => $newAchievements
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to complete session']);
    }
}

/**
 * Calculate performance score (0-100)
 */
function calculatePerformanceScore($scansPerMinute, $accuracyPercentage, $errorCount) {
    // Weight: 60% accuracy, 30% speed, 10% error penalty
    $accuracyScore = $accuracyPercentage * 0.6;

    // Speed score: 30+ scans/min = 30 points, scale down from there
    $speedScore = min(30, ($scansPerMinute / 30) * 30);

    // Error penalty: -1 point per error, max -10
    $errorPenalty = min(10, $errorCount);

    $score = $accuracyScore + $speedScore - $errorPenalty;

    return max(0, min(100, round($score)));
}

/**
 * Update daily staff performance aggregates
 */
function updateDailyPerformance($userId, $outletId) {
    global $conn;

    // Aggregate today's data
    $date = date('Y-m-d');

    $conn->query("
        INSERT INTO STAFF_PERFORMANCE_DAILY (date, user_id, outlet_id)
        VALUES ('$date', $userId, $outletId)
        ON DUPLICATE KEY UPDATE user_id = user_id
    ");

    // Recalculate aggregates
    $conn->query("
        UPDATE STAFF_PERFORMANCE_DAILY spd
        SET
            transfers_completed = (
                SELECT COUNT(*) FROM RECEIVING_SESSIONS rs
                WHERE rs.user_id = spd.user_id
                AND DATE(rs.completed_at) = spd.date
                AND rs.status = 'completed'
            ),
            avg_scans_per_minute = (
                SELECT AVG(scans_per_minute) FROM RECEIVING_SESSIONS rs
                WHERE rs.user_id = spd.user_id
                AND DATE(rs.completed_at) = spd.date
            ),
            accuracy_percentage = (
                SELECT AVG(accuracy_percentage) FROM RECEIVING_SESSIONS rs
                WHERE rs.user_id = spd.user_id
                AND DATE(rs.completed_at) = spd.date
            ),
            total_items_received = (
                SELECT SUM(items_scanned) FROM RECEIVING_SESSIONS rs
                WHERE rs.user_id = spd.user_id
                AND DATE(rs.completed_at) = spd.date
            ),
            error_count = (
                SELECT SUM(error_count) FROM RECEIVING_SESSIONS rs
                WHERE rs.user_id = spd.user_id
                AND DATE(rs.completed_at) = spd.date
            ),
            performance_score = (
                SELECT AVG(performance_score) FROM RECEIVING_SESSIONS rs
                WHERE rs.user_id = spd.user_id
                AND DATE(rs.completed_at) = spd.date
            )
        WHERE user_id = $userId AND date = '$date'
    ");
}

/**
 * Check for newly earned achievements
 */
function checkForAchievements($userId) {
    global $conn;

    $newAchievements = [];

    // Get all achievements user hasn't earned yet
    $achievements = $conn->query("
        SELECT * FROM ACHIEVEMENTS
        WHERE achievement_id NOT IN (
            SELECT achievement_id FROM USER_ACHIEVEMENTS WHERE user_id = $userId
        )
        AND is_active = 1
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($achievements as $achievement) {
        $criteria = json_decode($achievement['criteria'], true);

        if (meetsAchievementCriteria($userId, $criteria)) {
            // Award achievement
            $conn->query("
                INSERT INTO USER_ACHIEVEMENTS (user_id, achievement_id)
                VALUES ($userId, {$achievement['achievement_id']})
            ");

            $newAchievements[] = [
                'achievement_id' => $achievement['achievement_id'],
                'achievement_name' => $achievement['achievement_name'],
                'badge_icon' => $achievement['badge_icon'],
                'points' => $achievement['points'],
                'rarity' => $achievement['rarity']
            ];
        }
    }

    return $newAchievements;
}

/**
 * Check if user meets achievement criteria
 */
function meetsAchievementCriteria($userId, $criteria) {
    global $conn;

    switch ($criteria['type']) {
        case 'accuracy':
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count
                FROM RECEIVING_SESSIONS
                WHERE user_id = ?
                AND accuracy_percentage >= ?
                AND status = 'completed'
            ");
            $stmt->bind_param('id', $userId, $criteria['threshold']);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_assoc()['count'];
            return $count >= ($criteria['transfers'] ?? 1);

        case 'speed':
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count
                FROM RECEIVING_SESSIONS
                WHERE user_id = ?
                AND scans_per_minute >= ?
                AND status = 'completed'
            ");
            $stmt->bind_param('id', $userId, $criteria['scans_per_minute']);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_assoc()['count'];
            return $count >= ($criteria['transfers'] ?? 1);

        case 'volume':
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count
                FROM RECEIVING_SESSIONS
                WHERE user_id = ?
                AND DATE(completed_at) = CURDATE()
                AND status = 'completed'
            ");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_assoc()['count'];
            return $count >= $criteria['transfers_in_day'];

        case 'perfect':
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count
                FROM RECEIVING_SESSIONS
                WHERE user_id = ?
                AND accuracy_percentage >= 100
                AND total_items >= ?
                AND status = 'completed'
            ");
            $minItems = $criteria['min_items'] ?? 10;
            $stmt->bind_param('ii', $userId, $minItems);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_assoc()['count'];
            return $count >= 1;

        default:
            return false;
    }
}

/**
 * Get user performance data
 */
function getPerformance() {
    global $conn;

    $userId = $_GET['user_id'] ?? null;
    $period = $_GET['period'] ?? 'today'; // today, week, month, all_time

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'User ID required']);
        return;
    }

    // Build date filter
    $dateFilter = match($period) {
        'today' => "date = CURDATE()",
        'week' => "date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
        'month' => "date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
        default => "1=1"
    };

    // Get aggregate stats
    $stats = $conn->query("
        SELECT
            SUM(transfers_completed) as total_transfers,
            SUM(total_items_received) as total_items,
            AVG(accuracy_percentage) as avg_accuracy,
            AVG(avg_scans_per_minute) as avg_speed,
            SUM(error_count) as total_errors,
            AVG(performance_score) as avg_performance_score
        FROM STAFF_PERFORMANCE_DAILY
        WHERE user_id = $userId
        AND $dateFilter
    ")->fetch_assoc();

    // Get achievements
    $achievements = $conn->query("
        SELECT a.*, ua.earned_at
        FROM USER_ACHIEVEMENTS ua
        JOIN ACHIEVEMENTS a ON ua.achievement_id = a.achievement_id
        WHERE ua.user_id = $userId
        ORDER BY ua.earned_at DESC
    ")->fetch_all(MYSQLI_ASSOC);

    // Get rank
    $rank = $conn->query("
        SELECT COUNT(*) + 1 as rank
        FROM STAFF_PERFORMANCE_DAILY
        WHERE date = CURDATE()
        AND performance_score > (
            SELECT performance_score FROM STAFF_PERFORMANCE_DAILY
            WHERE user_id = $userId AND date = CURDATE()
        )
    ")->fetch_assoc()['rank'];

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'achievements' => $achievements,
        'rank' => $rank
    ]);
}

/**
 * Get leaderboard
 */
function getLeaderboard() {
    global $conn;

    $metric = $_GET['metric'] ?? 'overall'; // speed, accuracy, volume, overall
    $period = $_GET['period'] ?? 'daily'; // daily, weekly, monthly, all_time
    $limit = (int)($_GET['limit'] ?? 10);

    $periodDate = match($period) {
        'daily' => date('Y-m-d'),
        'weekly' => date('Y-m-d', strtotime('monday this week')),
        'monthly' => date('Y-m-01'),
        default => date('Y-m-d')
    };

    $leaderboard = $conn->query("
        SELECT
            l.*,
            u.full_name,
            u.email,
            o.name as outlet_name
        FROM LEADERBOARDS l
        JOIN users u ON l.user_id = u.id
        JOIN outlets o ON l.outlet_id = o.id
        WHERE l.period_type = '$period'
        AND l.period_date = '$periodDate'
        AND l.metric_type = '$metric'
        ORDER BY l.rank_position
        LIMIT $limit
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'leaderboard' => $leaderboard,
        'period' => $period,
        'metric' => $metric
    ]);
}

/**
 * Get suspicious scans for review
 */
function getSuspiciousScans() {
    global $conn;

    $limit = (int)($_GET['limit'] ?? 100);
    $minFraudScore = (int)($_GET['min_fraud_score'] ?? 30);

    $scans = $conn->query("
        SELECT * FROM V_SUSPICIOUS_SCANS
        WHERE fraud_score >= $minFraudScore
        ORDER BY scanned_at DESC
        LIMIT $limit
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'suspicious_scans' => $scans,
        'count' => count($scans)
    ]);
}
