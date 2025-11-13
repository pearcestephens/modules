<?php
/**
 * Aggregate suspicious signals into cis_suspicious_sessions
 *
 * Run via cron every 5 minutes:
 *   php /home/master/applications/jcepnzzkmj/public_html/modules/base/tools/aggregate_suspicious.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Thresholds (tune as needed)
$WINDOW_MINUTES = 15;           // analysis window
$RAPID_CLICKS_PER_MIN = 30;     // clicks/min threshold
$SCROLL_EVENTS_PER_MIN = 50;    // scrolls/min threshold
$VISIBILITY_TOGGLES = 10;       // toggles/15min threshold
$REQUEST_FAILS_15MIN = 10;      // request failures in window

$pdo = db();

// Collect sessions with any events in window
$stmt = $pdo->prepare(
    "SELECT DISTINCT session_id
       FROM cis_user_events
      WHERE created_at >= NOW() - INTERVAL :m MINUTE
        AND session_id IS NOT NULL"
);
$stmt->execute([':m' => $WINDOW_MINUTES]);
$sessions = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

if (!$sessions) {
    echo "No sessions to analyze\n";
    exit(0);
}

$ins = $pdo->prepare(
    "INSERT INTO cis_suspicious_sessions
        (session_id, user_id, devtools_detected, rapid_clicks, abnormal_scroll, automation_signals, ip_changes, notes)
     VALUES
        (:session_id, :user_id, :devtools, :rapid, :scroll, :auto, :ip_changes, :notes)
     ON DUPLICATE KEY UPDATE
        user_id = VALUES(user_id),
        devtools_detected = GREATEST(devtools_detected, VALUES(devtools_detected)),
        rapid_clicks = GREATEST(rapid_clicks, VALUES(rapid_clicks)),
        abnormal_scroll = GREATEST(abnormal_scroll, VALUES(abnormal_scroll)),
        automation_signals = GREATEST(automation_signals, VALUES(automation_signals)),
        ip_changes = GREATEST(ip_changes, VALUES(ip_changes)),
        last_seen_at = NOW()"
);

foreach ($sessions as $sid) {
    // Pull user id (latest event)
    $qUser = $pdo->prepare("SELECT user_id FROM cis_user_events WHERE session_id = ? ORDER BY id DESC LIMIT 1");
    $qUser->execute([$sid]);
    $userId = $qUser->fetchColumn();

    // DevTools
    $qDev = $pdo->prepare("SELECT COUNT(*) FROM cis_user_events WHERE session_id = ? AND event_type = 'suspicious' AND JSON_EXTRACT(event_data, '$.subtype') IN ('devtools','devtools_key','webdriver') AND created_at >= NOW() - INTERVAL :m MINUTE");
    $qDev->execute([$sid, ':m' => $WINDOW_MINUTES]);
    $devtools = ((int)$qDev->fetchColumn() > 0) ? 1 : 0;

    // Rapid clicks per minute in window
    $qClicks = $pdo->prepare("SELECT COUNT(*) FROM cis_user_events WHERE session_id = ? AND event_type = 'click' AND created_at >= NOW() - INTERVAL :m MINUTE");
    $qClicks->execute([$sid, ':m' => $WINDOW_MINUTES]);
    $clicks = (int)$qClicks->fetchColumn();
    $rapid = ($clicks >= $RAPID_CLICKS_PER_MIN * max(1, (int)ceil($WINDOW_MINUTES/1))) ? 1 : 0;

    // Abnormal scroll density
    $qScroll = $pdo->prepare("SELECT COUNT(*) FROM cis_user_events WHERE session_id = ? AND event_type = 'scroll' AND created_at >= NOW() - INTERVAL :m MINUTE");
    $qScroll->execute([$sid, ':m' => $WINDOW_MINUTES]);
    $scrolls = (int)$qScroll->fetchColumn();
    $abnormalScroll = ($scrolls >= $SCROLL_EVENTS_PER_MIN * max(1, (int)ceil($WINDOW_MINUTES/1))) ? 1 : 0;

    // Automation signals: frequent visibility toggles or request failures, webdriver flag
    $qVis = $pdo->prepare("SELECT COUNT(*) FROM cis_user_events WHERE session_id = ? AND event_type = 'visibility' AND created_at >= NOW() - INTERVAL :m MINUTE");
    $qVis->execute([$sid, ':m' => $WINDOW_MINUTES]);
    $visToggles = (int)$qVis->fetchColumn();

    $qReqFail = $pdo->prepare("SELECT COUNT(*) FROM cis_user_events WHERE session_id = ? AND event_type IN ('request_fail','network_error') AND created_at >= NOW() - INTERVAL :m MINUTE");
    $qReqFail->execute([$sid, ':m' => $WINDOW_MINUTES]);
    $reqFails = (int)$qReqFail->fetchColumn();

    $automation = ($visToggles >= $VISIBILITY_TOGGLES || $reqFails >= $REQUEST_FAILS_15MIN) ? 1 : 0;

    // IP changes in last 24h
    $qIP = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) FROM cis_user_events WHERE session_id = ? AND created_at >= NOW() - INTERVAL 1 DAY");
    $qIP->execute([$sid]);
    $ipChanges = max(0, (int)$qIP->fetchColumn() - 1);

    $notes = null;
    if ($devtools || $rapid || $abnormalScroll || $automation || $ipChanges > 0) {
        $notes = json_encode([
            'clicks' => $clicks,
            'scrolls' => $scrolls,
            'vis_toggles' => $visToggles,
            'req_fails' => $reqFails
        ]);
    }

    $ins->execute([
        ':session_id' => $sid,
        ':user_id' => $userId ?: null,
        ':devtools' => $devtools,
        ':rapid' => $rapid,
        ':scroll' => $abnormalScroll,
        ':auto' => $automation,
        ':ip_changes' => $ipChanges,
        ':notes' => $notes
    ]);
}

echo "Analyzed " . count($sessions) . " session(s)\n";
