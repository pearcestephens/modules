<?php
declare(strict_types=1);

namespace StaffEmailHub\Services;

use PDO;
use Exception;

/**
 * CustomerHubService - All-in-one customer view with history & communications
 *
 * Features: Full profile, purchase history, communication log, notes, tags, VIP/flags
 *
 * @version 1.0.0
 */
class CustomerHubService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get complete customer profile with all associated data
     */
    public function getCustomerProfile(int $customerId): array
    {
        try {
            // Get profile
            $stmt = $this->db->prepare("SELECT * FROM customer_hub_profile WHERE customer_id = ?");
            $stmt->execute([$customerId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$profile) {
                return $this->error('Customer not found');
            }

            // Get purchase history
            $purchases = $this->getPurchaseHistory($customerId);

            // Get communication log
            $communications = $this->getCommunicationLog($customerId);

            // Get ID verification status
            $idStatus = $this->getIdVerificationStatus($customerId);

            // Get related emails
            $emails = $this->getCustomerEmails($customerId);

            return [
                'success' => true,
                'profile' => $profile,
                'purchases' => $purchases['purchases'] ?? [],
                'purchase_count' => $purchases['count'] ?? 0,
                'communications' => $communications['communications'] ?? [],
                'id_verification' => $idStatus,
                'emails' => $emails['emails'] ?? [],
                'email_count' => $emails['count'] ?? 0,
                'total_spent' => (float)($profile['total_spent'] ?? 0),
                'loyalty_points' => (int)($profile['loyalty_points'] ?? 0),
                'is_vip' => (bool)$profile['is_vip'],
                'is_flagged' => (bool)$profile['is_flagged'],
                'last_interaction' => $this->getLastInteraction($customerId)
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Get purchase history
     */
    public function getPurchaseHistory(int $customerId, int $limit = 50): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, vend_sale_id, outlet_id, sale_date, total_amount, item_count, items_json, payment_method
                FROM customer_purchase_history
                WHERE customer_id = ?
                ORDER BY sale_date DESC
                LIMIT ?
            ");
            $stmt->execute([$customerId, $limit]);
            $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'purchases' => $purchases,
                'count' => count($purchases)
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Get communication log
     */
    public function getCommunicationLog(int $customerId, int $limit = 100): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, email_id, communication_type, direction, subject, summary, staff_id, tags, created_at
                FROM customer_communication_log
                WHERE customer_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$customerId, $limit]);
            $communications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'communications' => $communications,
                'count' => count($communications)
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Get customer emails
     */
    public function getCustomerEmails(int $customerId, int $limit = 50): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, trace_id, from_staff_id, subject, status, is_r18_flagged,
                       created_at, sent_at, read_at, tags
                FROM staff_emails
                WHERE customer_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$customerId, $limit]);
            $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'emails' => $emails,
                'count' => count($emails)
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Get ID verification status
     */
    public function getIdVerificationStatus(int $customerId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM customer_id_uploads
                WHERE customer_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$customerId]);
            $upload = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$upload) {
                return [
                    'verified' => false,
                    'status' => 'not_provided',
                    'upload_id' => null
                ];
            }

            return [
                'verified' => (bool)$upload['is_verified'],
                'status' => $upload['verification_status'],
                'upload_id' => $upload['id'],
                'id_type' => $upload['id_type'],
                'verified_at' => $upload['verified_at'],
                'expires_at' => $upload['expires_at'],
                'is_expired' => (bool)$upload['is_expired'],
                'verification_score' => $upload['verification_score']
            ];
        } catch (Exception $e) {
            return ['verified' => false, 'status' => 'error'];
        }
    }

    /**
     * Add note to customer profile
     */
    public function addNote(int $customerId, string $note, int $staffId): array
    {
        try {
            $profile = $this->getCustomerProfile($customerId);
            if (!$profile['success']) {
                return $profile;
            }

            $existingNotes = $profile['profile']['notes'] ? $profile['profile']['notes'] . "\n" : '';
            $newNotes = $existingNotes . "[" . date('Y-m-d H:i:s') . " - Staff #$staffId] " . $note;

            $stmt = $this->db->prepare("
                UPDATE customer_hub_profile
                SET notes = ?, updated_at = NOW()
                WHERE customer_id = ?
            ");
            $stmt->execute([$newNotes, $customerId]);

            return ['success' => true, 'customer_id' => $customerId];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Flag customer (with reason)
     */
    public function flagCustomer(int $customerId, string $reason): array
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE customer_hub_profile
                SET is_flagged = TRUE, flag_reason = ?, updated_at = NOW()
                WHERE customer_id = ?
            ");
            $stmt->execute([$reason, $customerId]);

            return ['success' => true, 'flagged' => true];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Set VIP status
     */
    public function setVIP(int $customerId, bool $isVip): array
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE customer_hub_profile
                SET is_vip = ?, updated_at = NOW()
                WHERE customer_id = ?
            ");
            $stmt->execute([$isVip ? 1 : 0, $customerId]);

            return ['success' => true, 'is_vip' => $isVip];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Add tag to customer
     */
    public function addTag(int $customerId, string $tag): array
    {
        try {
            $profile = $this->getCustomerProfile($customerId);
            $tags = $profile['profile']['tags'] ? json_decode($profile['profile']['tags'], true) : [];

            if (!in_array($tag, $tags)) {
                $tags[] = $tag;
            }

            $stmt = $this->db->prepare("
                UPDATE customer_hub_profile
                SET tags = ?, updated_at = NOW()
                WHERE customer_id = ?
            ");
            $stmt->execute([json_encode($tags), $customerId]);

            return ['success' => true, 'tags' => $tags];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Record communication
     */
    public function recordCommunication(int $customerId, array $data): array
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO customer_communication_log
                (customer_id, email_id, communication_type, direction, subject, summary, staff_id, tags, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $customerId,
                $data['email_id'] ?? null,
                $data['type'] ?? 'email',
                $data['direction'] ?? 'outbound',
                $data['subject'] ?? '',
                $data['summary'] ?? '',
                $data['staff_id'] ?? null,
                json_encode($data['tags'] ?? [])
            ]);

            return ['success' => true];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Get last interaction timestamp
     */
    private function getLastInteraction(int $customerId): ?string
    {
        try {
            // Check last email
            $stmt = $this->db->prepare("
                SELECT created_at FROM staff_emails
                WHERE customer_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$customerId]);
            $email = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check last communication
            $commStmt = $this->db->prepare("
                SELECT created_at FROM customer_communication_log
                WHERE customer_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $commStmt->execute([$customerId]);
            $comm = $commStmt->fetch(PDO::FETCH_ASSOC);

            $dates = array_filter([
                $email['created_at'] ?? null,
                $comm['created_at'] ?? null
            ]);

            if (empty($dates)) {
                return null;
            }

            rsort($dates);
            return $dates[0];
        } catch (Exception $e) {
            return null;
        }
    }

    private function error(string $message): array
    {
        error_log("[CustomerHubService] {$message}");
        return ['success' => false, 'error' => $message];
    }
}
