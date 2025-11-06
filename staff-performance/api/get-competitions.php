<?php
/**
 * Staff Performance API - Get Competitions
 *
 * Returns competition data with standings
 *
 * @package CIS\Modules\StaffPerformance\API
 * @version 1.0.0
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap.php';

try {
    $competition_id = $_GET['competition_id'] ?? null;

    if ($competition_id) {
        // Get specific competition with full details
        $stmt = $db->prepare("
            SELECT c.*,
                   COUNT(cp.participant_id) as participant_count
            FROM competitions c
            LEFT JOIN competition_participants cp ON c.competition_id = cp.competition_id
            WHERE c.competition_id = ?
            GROUP BY c.competition_id
        ");
        $stmt->execute([$competition_id]);
        $competition = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$competition) {
            throw new Exception('Competition not found');
        }

        // Get top 10 participants
        $stmt = $db->prepare("
            SELECT cp.*, sa.full_name, sa.store_id, o.name as store_name
            FROM competition_participants cp
            JOIN staff_accounts sa ON cp.staff_id = sa.staff_id
            LEFT JOIN outlets o ON sa.store_id = o.outlet_id
            WHERE cp.competition_id = ?
            ORDER BY cp.rank ASC
            LIMIT 10
        ");
        $stmt->execute([$competition_id]);
        $standings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get user's standing
        $stmt = $db->prepare("
            SELECT cp.*, sa.full_name
            FROM competition_participants cp
            JOIN staff_accounts sa ON cp.staff_id = sa.staff_id
            WHERE cp.competition_id = ? AND cp.staff_id = ?
        ");
        $stmt->execute([$competition_id, $current_user_id]);
        $userStanding = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'competition' => $competition,
                'standings' => $standings,
                'user_standing' => $userStanding
            ]
        ]);

    } else {
        // Get all active competitions
        $stmt = $db->prepare("
            SELECT c.*,
                   COUNT(cp.participant_id) as participant_count,
                   cp2.score as user_score,
                   cp2.rank as user_rank
            FROM competitions c
            LEFT JOIN competition_participants cp ON c.competition_id = cp.competition_id
            LEFT JOIN competition_participants cp2 ON c.competition_id = cp2.competition_id AND cp2.staff_id = ?
            WHERE c.status = 'active' AND c.start_date <= NOW() AND c.end_date >= NOW()
            GROUP BY c.competition_id
            ORDER BY c.end_date ASC
        ");
        $stmt->execute([$current_user_id]);
        $active = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get upcoming
        $stmt = $db->query("
            SELECT * FROM competitions
            WHERE status = 'pending' AND start_date > NOW()
            ORDER BY start_date ASC
            LIMIT 5
        ");
        $upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'active' => $active,
                'upcoming' => $upcoming
            ]
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch competitions',
        'message' => $e->getMessage()
    ]);
}
