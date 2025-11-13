<?php
require_once __DIR__ . '/../../app.php';

$names = [
    'Arent Roggeveen' => [50.00, 50.00],
    'Ashleigh Demus' => [50.00, 50.00, 50.00],
    'Brydan Downs' => [50.00, 50.00, 50.00],
    'Cassidy Rogers' => [45.32, 45.32],
    'Dylan Leswick' => [22.02, 22.02],
    'Dylan Steinz' => [50.00, 50.00, 50.00],
    'Halana Carr' => [4.44, 11.01, 11.01],
    "Heath O'Malley" => [15.41, 15.41, 15.41],
    'Ian Paul' => [11.01, 33.03, 33.03],
    'Jayden Garrett-Macfarlane' => [50.00, 50.00],
    'Jeremiah Hawkins' => [50.00, 50.00],
    'Jessica Jubb' => [3.22, 21.45, 21.45],
    'Joshua Foreman' => [50.00, 50.00, 50.00],
    'Kiel Newman' => [50.00, 50.00, 50.00],
    'Lawrence Archbold' => [50.00, 50.00, 50.00],
    'Lawrence Johnson' => [50.00, 50.00, 50.00],
    'Matthew Hanright' => [44.04, 44.04],
    'Nikita Shannon' => [23.21, 23.21, 23.21],
    'Quinn Kereopa' => [50.00, 50.00, 50.00],
    'Tania Paul' => [5.00, 5.00],
    'Van Tilsley' => [50.00, 50.00],
    'Zaque Mckenna' => [50.00, 12.99, 12.99],
];

echo "Looking up user IDs...\n\n";

$db = null;
try {
    $db = new PDO("mysql:host=127.0.0.1;dbname=jcepnzzkmj", "jcepnzzkmj", "wprKh9Jq63");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    foreach ($names as $name => $amounts) {
        $stmt = $db->prepare("SELECT id, full_name FROM users WHERE full_name LIKE ? ORDER BY id LIMIT 1");
        $stmt->execute(['%' . $name . '%']);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($result && count($result) > 0) {
            $userId = $result[0]['id'];
            $fullName = $result[0]['full_name'];
            $total = array_sum($amounts);
            echo "    [$userId, $total, '$fullName - Register deposits'],\n";
        } else {
            echo "    // NOT FOUND: $name\n";
        }
    }
} finally {
    // ✅ CRITICAL FIX: Always cleanup PDO connection to prevent connection leaks
    $db = null;
}
