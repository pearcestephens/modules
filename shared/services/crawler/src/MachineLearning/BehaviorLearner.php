<?php

declare(strict_types=1);
/**
 * BehaviorLearner - Q-Learning Coordinator for Behavioral Optimization.
 *
 * Features:
 * - Q-Learning reinforcement learning
 * - Experience replay management
 * - Policy optimization
 * - Multi-armed bandit for profile selection
 *
 * @version 2.0.0
 */

namespace CIS\SharedServices\Crawler\MachineLearning;

use Psr\Log\LoggerInterface;

use function array_slice;
use function count;

use const PHP_FLOAT_MAX;

class BehaviorLearner
{
    private LoggerInterface $logger;

    private array $config;

    private array $qTable = [];

    private array $rewardHistory = [];

    private float $epsilon;

    public function __construct(LoggerInterface $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'learning_rate'   => 0.1,
            'discount_factor' => 0.9,
            'epsilon'         => 0.1,
            'epsilon_decay'   => 0.995,
            'epsilon_min'     => 0.01,
        ], $config);

        $this->epsilon = $this->config['epsilon'];
    }

    /**
     * Update Q-Learning table with new experience.
     */
    public function learn(string $state, string $action, float $reward, string $nextState): void
    {
        $currentQ = $this->getQValue($state, $action);
        $maxNextQ = $this->getMaxQValue($nextState);

        // Q-Learning update: Q(s,a) = Q(s,a) + α[r + γ*max(Q(s',a')) - Q(s,a)]
        $newQ = $currentQ + $this->config['learning_rate'] * (
            $reward + $this->config['discount_factor'] * $maxNextQ - $currentQ
        );

        $this->setQValue($state, $action, $newQ);

        $this->recordReward($reward);
        $this->decayEpsilon();

        $this->logger->debug('Q-Learning update', [
            'state'   => $state,
            'action'  => $action,
            'reward'  => round($reward, 3),
            'old_q'   => round($currentQ, 3),
            'new_q'   => round($newQ, 3),
            'epsilon' => round($this->epsilon, 3),
        ]);
    }

    /**
     * Get optimal action for state (epsilon-greedy).
     */
    public function selectAction(string $state, array $availableActions): string
    {
        // Epsilon-greedy: explore or exploit
        if ($this->randomFloat() < $this->epsilon) {
            // Explore: random action
            return $availableActions[array_rand($availableActions)];
        }

        // Exploit: best known action
        $bestAction = null;
        $bestValue  = -PHP_FLOAT_MAX;

        foreach ($availableActions as $action) {
            $qValue = $this->getQValue($state, $action);
            if ($qValue > $bestValue) {
                $bestValue  = $qValue;
                $bestAction = $action;
            }
        }

        return $bestAction ?? $availableActions[0];
    }

    /**
     * Get Q-value for state-action pair.
     */
    public function getQValue(string $state, string $action): float
    {
        return $this->qTable[$state][$action] ?? 0.0;
    }

    /**
     * Get maximum Q-value for state.
     */
    public function getMaxQValue(string $state): float
    {
        if (!isset($this->qTable[$state]) || empty($this->qTable[$state])) {
            return 0.0;
        }

        return max($this->qTable[$state]);
    }

    /**
     * Multi-armed bandit for profile selection (UCB1 algorithm).
     */
    public function selectProfile(array $profiles): string
    {
        $totalAttempts = array_sum(array_column($profiles, 'attempts'));

        if ($totalAttempts === 0) {
            return array_keys($profiles)[array_rand($profiles)];
        }

        $bestProfile = null;
        $bestScore   = -PHP_FLOAT_MAX;

        foreach ($profiles as $name => $profile) {
            if ($profile['attempts'] === 0) {
                return $name; // Try unexplored profiles first
            }

            // UCB1 formula: average_reward + sqrt(2 * ln(total_attempts) / profile_attempts)
            $avgReward   = $profile['total_reward'] / $profile['attempts'];
            $exploration = sqrt(2 * log($totalAttempts) / $profile['attempts']);
            $score       = $avgReward + $exploration;

            if ($score > $bestScore) {
                $bestScore   = $score;
                $bestProfile = $name;
            }
        }

        return $bestProfile;
    }

    /**
     * Get learning statistics.
     */
    public function getStats(): array
    {
        $recentRewards = array_slice($this->rewardHistory, -100);

        return [
            'q_table_size'      => count($this->qTable),
            'total_states'      => count($this->qTable),
            'total_experiences' => count($this->rewardHistory),
            'epsilon'           => round($this->epsilon, 3),
            'avg_reward_recent' => !empty($recentRewards) ? round(array_sum($recentRewards) / count($recentRewards), 3) : 0,
            'total_reward'      => round(array_sum($this->rewardHistory), 2),
        ];
    }

    /**
     * Export Q-table for persistence.
     */
    public function exportQTable(): array
    {
        return $this->qTable;
    }

    /**
     * Import Q-table from persistence.
     */
    public function importQTable(array $qTable): void
    {
        $this->qTable = $qTable;

        $this->logger->info('Q-table imported', [
            'states' => count($qTable),
        ]);
    }

    /**
     * Reset learning state.
     */
    public function reset(): void
    {
        $this->qTable        = [];
        $this->rewardHistory = [];
        $this->epsilon       = $this->config['epsilon'];

        $this->logger->info('Behavior learner reset');
    }

    // ============================================================================
    // PRIVATE METHODS
    // ============================================================================

    private function setQValue(string $state, string $action, float $value): void
    {
        if (!isset($this->qTable[$state])) {
            $this->qTable[$state] = [];
        }
        $this->qTable[$state][$action] = $value;
    }

    private function recordReward(float $reward): void
    {
        $this->rewardHistory[] = $reward;

        // Limit history size
        if (count($this->rewardHistory) > 10000) {
            $this->rewardHistory = array_slice($this->rewardHistory, -5000);
        }
    }

    private function decayEpsilon(): void
    {
        $this->epsilon = max(
            $this->config['epsilon_min'],
            $this->epsilon * $this->config['epsilon_decay'],
        );
    }

    private function randomFloat(): float
    {
        return mt_rand() / mt_getrandmax();
    }
}
