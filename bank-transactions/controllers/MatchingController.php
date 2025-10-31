<?php
/**
 * Bank Transactions - Matching Controller
 *
 * Handles matching-related operations
 */

declare(strict_types=1);

namespace BankTransactions\Controllers;

class MatchingController extends BaseController {

    private $matchingEngine;

    public function __construct() {
        parent::__construct();
        require_once __DIR__ . '/../lib/MatchingEngine.php';
        $this->matchingEngine = new \CIS\BankTransactions\Lib\MatchingEngine();
    }

    public function suggestions(): void {
        $this->render('matching/suggestions', [
            'title' => 'Match Suggestions',
        ]);
    }

    public function manual(): void {
        $this->render('matching/manual', [
            'title' => 'Manual Matching',
        ]);
    }
}
?>
