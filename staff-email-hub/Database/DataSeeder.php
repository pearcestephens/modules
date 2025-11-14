<?php

declare(strict_types=1);

namespace StaffEmailHub\Database;

use PDO;
use Exception;

/**
 * DataSeeder - Populates the Staff Email Hub with sample data
 *
 * Creates realistic customer, order, and email data for testing and demonstrations
 */
class DataSeeder
{
    private PDO $db;
    private bool $isDemoMode = true;

    public function __construct(PDO $db, bool $isDemoMode = true)
    {
        $this->db = $db;
        $this->isDemoMode = $isDemoMode;
    }

    /**
     * Run all seeding operations
     */
    public function seed(): array
    {
        try {
            $results = [
                'customers' => $this->seedCustomers(),
                'orders' => $this->seedOrders(),
                'communications' => $this->seedCommunications(),
                'emails' => $this->seedEmails(),
            ];

            return [
                'success' => true,
                'message' => 'Sample data seeded successfully',
                'results' => $results,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Seeding failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Seed customer data
     */
    private function seedCustomers(): array
    {
        $customers = [
            [
                'vend_customer_id' => 'vend-001',
                'first_name' => 'John',
                'last_name' => 'Smith',
                'email' => 'john.smith@example.com',
                'alt_email' => 'jsmith@gmail.com',
                'phone' => '+64 21 123 4567',
                'alt_phone' => '+64 9 555 1234',
                'date_of_birth' => '1990-05-15',
                'id_verified' => true,
                'is_vip' => true,
                'total_spent' => 3450.50,
                'notes' => 'Premium customer, regular weekly purchases',
            ],
            [
                'vend_customer_id' => 'vend-002',
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'email' => 'sarah.johnson@example.com',
                'alt_email' => 'sarahj@hotmail.com',
                'phone' => '+64 21 987 6543',
                'alt_phone' => null,
                'date_of_birth' => '1988-03-22',
                'id_verified' => true,
                'is_vip' => false,
                'total_spent' => 1250.00,
                'notes' => 'Prefers email contact',
            ],
            [
                'vend_customer_id' => 'vend-003',
                'first_name' => 'Mike',
                'last_name' => 'Williams',
                'email' => 'mike.w@example.com',
                'alt_email' => null,
                'phone' => '+64 21 555 9999',
                'alt_phone' => null,
                'date_of_birth' => '1992-11-08',
                'id_verified' => false,
                'is_vip' => false,
                'total_spent' => 850.75,
                'notes' => 'Pending ID verification',
            ],
            [
                'vend_customer_id' => 'vend-004',
                'first_name' => 'Emma',
                'last_name' => 'Brown',
                'email' => 'emma.brown@example.com',
                'alt_email' => 'emmab@yahoo.com',
                'phone' => '+64 21 444 7777',
                'alt_phone' => null,
                'date_of_birth' => '1995-07-30',
                'id_verified' => true,
                'is_vip' => true,
                'total_spent' => 2890.00,
                'notes' => 'High-value customer, bulk orders',
            ],
            [
                'vend_customer_id' => 'vend-005',
                'first_name' => 'David',
                'last_name' => 'Taylor',
                'email' => 'david.taylor@example.com',
                'alt_email' => null,
                'phone' => '+64 21 333 2222',
                'alt_phone' => null,
                'date_of_birth' => '1987-01-12',
                'id_verified' => true,
                'is_vip' => false,
                'total_spent' => 1520.25,
                'notes' => 'Regular customer, consistent purchases',
            ],
        ];

        $inserted = 0;
        foreach ($customers as $customer) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO customer_hub_profile
                    (vend_customer_id, first_name, last_name, email, alt_email, phone, alt_phone,
                     date_of_birth, id_verified, is_vip, total_spent, notes, is_demo_data, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");

                $stmt->execute([
                    $customer['vend_customer_id'],
                    $customer['first_name'],
                    $customer['last_name'],
                    $customer['email'],
                    $customer['alt_email'],
                    $customer['phone'],
                    $customer['alt_phone'],
                    $customer['date_of_birth'],
                    $customer['id_verified'],
                    $customer['is_vip'],
                    $customer['total_spent'],
                    $customer['notes'],
                    $this->isDemoMode,
                ]);

                $inserted++;
            } catch (Exception $e) {
                error_log("[DataSeeder] Failed to insert customer: " . $e->getMessage());
            }
        }

        return [
            'inserted' => $inserted,
            'total' => count($customers),
        ];
    }

    /**
     * Seed purchase order data
     */
    private function seedOrders(): array
    {
        // Get customer IDs
        $stmt = $this->db->query("SELECT id FROM customer_hub_profile WHERE is_demo_data = true");
        $customers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($customers)) {
            return ['inserted' => 0, 'error' => 'No demo customers found'];
        }

        $orders = [];
        $products = [
            ['name' => 'SMOK Nord 4', 'price' => 89.99],
            ['name' => 'Vape Liquid 100ML - Berry Blast', 'price' => 24.99],
            ['name' => 'Coils Pack (5pcs)', 'price' => 19.99],
            ['name' => 'Vape Pod Kit', 'price' => 45.50],
            ['name' => 'Nicotine Salts 10ML', 'price' => 15.99],
            ['name' => 'Battery 18650 2800mah', 'price' => 8.99],
            ['name' => 'Vaping Starter Kit', 'price' => 120.00],
            ['name' => 'Tank Replacement Coils', 'price' => 22.50],
        ];

        $inserted = 0;

        foreach ($customers as $customerId) {
            // 2-5 orders per customer
            $orderCount = rand(2, 5);

            for ($i = 0; $i < $orderCount; $i++) {
                try {
                    // Random order date in last 6 months
                    $days = rand(1, 180);
                    $orderDate = date('Y-m-d H:i:s', strtotime("-$days days"));

                    // Random products
                    $productCount = rand(1, 4);
                    $total = 0;
                    $items = [];

                    for ($j = 0; $j < $productCount; $j++) {
                        $product = $products[array_rand($products)];
                        $qty = rand(1, 3);
                        $total += $product['price'] * $qty;
                        $items[] = [
                            'name' => $product['name'],
                            'quantity' => $qty,
                            'price' => $product['price'],
                        ];
                    }

                    $stmt = $this->db->prepare("
                        INSERT INTO customer_purchase_history
                        (customer_id, vend_order_id, order_date, total_amount, items, status, is_demo_data, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $customerId,
                        'ORDER-' . uniqid(),
                        $orderDate,
                        $total,
                        json_encode($items),
                        'completed',
                        $this->isDemoMode,
                        $orderDate,
                    ]);

                    $inserted++;
                } catch (Exception $e) {
                    error_log("[DataSeeder] Failed to insert order: " . $e->getMessage());
                }
            }
        }

        return [
            'inserted' => $inserted,
            'customers' => count($customers),
        ];
    }

    /**
     * Seed communication log data
     */
    private function seedCommunications(): array
    {
        // Get customer IDs
        $stmt = $this->db->query("SELECT id FROM customer_hub_profile WHERE is_demo_data = true");
        $customers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($customers)) {
            return ['inserted' => 0, 'error' => 'No demo customers found'];
        }

        $communications = [
            'Phone call - Product inquiry',
            'Email - Order confirmation',
            'Email - Shipping notification',
            'Phone call - Follow-up',
            'In-person - Store visit',
            'Email - Customer satisfaction survey',
            'System - Order placed',
            'Email - Promotional offer',
            'Phone call - Support request',
            'Email - Receipt',
        ];

        $inserted = 0;

        foreach ($customers as $customerId) {
            $commCount = rand(3, 8);

            for ($i = 0; $i < $commCount; $i++) {
                try {
                    $days = rand(1, 180);
                    $commDate = date('Y-m-d H:i:s', strtotime("-$days days"));

                    $stmt = $this->db->prepare("
                        INSERT INTO customer_communication_log
                        (customer_id, type, subject, notes, created_at, is_demo_data)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");

                    $comm = $communications[array_rand($communications)];
                    $type = strpos($comm, 'Phone') !== false ? 'phone' :
                           (strpos($comm, 'Email') !== false ? 'email' : 'in_person');

                    $stmt->execute([
                        $customerId,
                        $type,
                        $comm,
                        'Demo communication record',
                        $commDate,
                        $this->isDemoMode,
                    ]);

                    $inserted++;
                } catch (Exception $e) {
                    error_log("[DataSeeder] Failed to insert communication: " . $e->getMessage());
                }
            }
        }

        return [
            'inserted' => $inserted,
            'customers' => count($customers),
        ];
    }

    /**
     * Seed email data
     */
    private function seedEmails(): array
    {
        // Get customer IDs
        $stmt = $this->db->query("SELECT id, email, first_name FROM customer_hub_profile WHERE is_demo_data = true");
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($customers)) {
            return ['inserted' => 0, 'error' => 'No demo customers found'];
        }

        $emailSubjects = [
            'Order #123456 - Shipped',
            'Thank you for your purchase',
            'New vaping products in stock',
            'Your receipt',
            'Follow-up: How was your experience?',
            'Limited time offer - 20% off',
            'Product recommendation',
            'We miss you - come back for 15% off',
            'Your account security',
            'Newsletter: Latest vaping trends',
        ];

        $inserted = 0;
        $staffId = 1; // Assuming staff ID 1 exists

        foreach ($customers as $customer) {
            $emailCount = rand(2, 6);

            for ($i = 0; $i < $emailCount; $i++) {
                try {
                    $days = rand(1, 180);
                    $emailDate = date('Y-m-d H:i:s', strtotime("-$days days"));

                    $stmt = $this->db->prepare("
                        INSERT INTO staff_emails
                        (staff_id, customer_id, subject, from_address, to_address, body,
                         message_id, status, is_demo_data, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");

                    $subject = $emailSubjects[array_rand($emailSubjects)];
                    $body = "Dear " . $customer['first_name'] . ",\n\n" .
                           "This is a demo email. In a real scenario, this would contain the full email content.\n\n" .
                           "Thank you for your business!\n\n" .
                           "Best regards,\nThe Vape Shed Team";

                    $stmt->execute([
                        $staffId,
                        $customer['id'],
                        $subject,
                        'noreply@vapeshed.co.nz',
                        $customer['email'],
                        $body,
                        'MSG-' . uniqid(),
                        rand(0, 1) === 0 ? 'sent' : 'draft',
                        $this->isDemoMode,
                        $emailDate,
                    ]);

                    $inserted++;
                } catch (Exception $e) {
                    error_log("[DataSeeder] Failed to insert email: " . $e->getMessage());
                }
            }
        }

        return [
            'inserted' => $inserted,
            'customers' => count($customers),
        ];
    }

    /**
     * Clear all demo data
     */
    public function clearDemoData(): array
    {
        try {
            $tables = [
                'staff_emails',
                'customer_purchase_history',
                'customer_communication_log',
                'customer_hub_profile',
            ];

            foreach ($tables as $table) {
                $stmt = $this->db->prepare("DELETE FROM $table WHERE is_demo_data = true");
                $stmt->execute();
            }

            return [
                'success' => true,
                'message' => 'Demo data cleared successfully',
                'tables_cleared' => count($tables),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to clear demo data: ' . $e->getMessage(),
            ];
        }
    }
}
