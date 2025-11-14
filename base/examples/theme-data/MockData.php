<?php
/**
 * Mock Data Generator for Theme Previews
 *
 * Generates realistic-looking data for CIS theme demos
 */

declare(strict_types=1);

namespace CIS\Themes\Data;

class MockData
{
    /**
     * Get mock stores
     */
    public static function getStores(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Vape Shed - Auckland CBD',
                'location' => '123 Queen Street, Auckland',
                'manager' => 'Sarah Johnson',
                'phone' => '09-123-4567',
                'status' => 'online',
                'sales_today' => 2850.50,
                'orders_pending' => 12,
                'stock_alerts' => 3,
                'staff_online' => 4,
                'image' => 'https://picsum.photos/seed/store1/400/300',
            ],
            [
                'id' => 2,
                'name' => 'Vape Shed - Wellington',
                'location' => '456 Lambton Quay, Wellington',
                'manager' => 'Mike Chen',
                'phone' => '04-987-6543',
                'status' => 'online',
                'sales_today' => 3200.75,
                'orders_pending' => 8,
                'stock_alerts' => 1,
                'staff_online' => 3,
                'image' => 'https://picsum.photos/seed/store2/400/300',
            ],
            [
                'id' => 3,
                'name' => 'Vape Shed - Christchurch',
                'location' => '789 Colombo Street, Christchurch',
                'manager' => 'Emma Davis',
                'phone' => '03-555-1234',
                'status' => 'online',
                'sales_today' => 1950.25,
                'orders_pending' => 15,
                'stock_alerts' => 5,
                'staff_online' => 2,
                'image' => 'https://picsum.photos/seed/store3/400/300',
            ],
        ];
    }

    /**
     * Get mock products
     */
    public static function getProducts(): array
    {
        return [
            ['id' => 1, 'name' => 'JUUL Starter Kit', 'sku' => 'JUL-001', 'price' => 49.99, 'stock' => 45, 'sales' => 156, 'image' => 'https://picsum.photos/seed/prod1/200/200'],
            ['id' => 2, 'name' => 'Vaporesso XROS 3', 'sku' => 'VAP-203', 'price' => 39.99, 'stock' => 32, 'sales' => 143, 'image' => 'https://picsum.photos/seed/prod2/200/200'],
            ['id' => 3, 'name' => 'SMOK Nord 5', 'sku' => 'SMK-305', 'price' => 54.99, 'stock' => 28, 'sales' => 98, 'image' => 'https://picsum.photos/seed/prod3/200/200'],
            ['id' => 4, 'name' => 'Caliburn G2', 'sku' => 'CAL-102', 'price' => 34.99, 'stock' => 67, 'sales' => 187, 'image' => 'https://picsum.photos/seed/prod4/200/200'],
            ['id' => 5, 'name' => 'Voopoo Drag X', 'sku' => 'VOO-410', 'price' => 69.99, 'stock' => 15, 'sales' => 76, 'image' => 'https://picsum.photos/seed/prod5/200/200'],
        ];
    }

    /**
     * Get mock orders
     */
    public static function getOrders(): array
    {
        return [
            ['id' => 1001, 'customer' => 'James Wilson', 'store' => 'Auckland CBD', 'total' => 149.97, 'status' => 'pending', 'time' => '10 mins ago'],
            ['id' => 1002, 'customer' => 'Lisa Anderson', 'store' => 'Wellington', 'total' => 89.98, 'status' => 'processing', 'time' => '25 mins ago'],
            ['id' => 1003, 'customer' => 'Robert Taylor', 'store' => 'Christchurch', 'total' => 234.95, 'status' => 'shipped', 'time' => '1 hour ago'],
            ['id' => 1004, 'customer' => 'Jennifer Lee', 'store' => 'Auckland CBD', 'total' => 64.99, 'status' => 'pending', 'time' => '2 hours ago'],
            ['id' => 1005, 'customer' => 'Michael Brown', 'store' => 'Wellington', 'total' => 179.96, 'status' => 'completed', 'time' => '3 hours ago'],
        ];
    }

    /**
     * Get mock news feed
     */
    public static function getNewsFeed(): array
    {
        return [
            [
                'type' => 'announcement',
                'title' => 'New Product Launch: JUUL2 Available Now',
                'content' => 'We\'re excited to announce the arrival of JUUL2 devices across all stores. Featuring improved battery life and enhanced flavor delivery.',
                'author' => 'Head Office',
                'time' => '2 hours ago',
                'likes' => 24,
                'comments' => 8,
                'image' => 'https://picsum.photos/seed/news1/600/400',
            ],
            [
                'type' => 'achievement',
                'title' => 'Wellington Store Breaks Sales Record!',
                'content' => 'Congratulations to the Wellington team for achieving $15,000 in daily sales - a new company record! Great job team!',
                'author' => 'Regional Manager',
                'time' => '5 hours ago',
                'likes' => 45,
                'comments' => 12,
                'image' => null,
            ],
            [
                'type' => 'training',
                'title' => 'Mandatory Compliance Training - Due Friday',
                'content' => 'All staff must complete the updated vaping regulations training module by end of week. Access via the training portal.',
                'author' => 'HR Department',
                'time' => '1 day ago',
                'likes' => 12,
                'comments' => 3,
                'image' => null,
            ],
        ];
    }

    /**
     * Get mock metrics
     */
    public static function getMetrics(): array
    {
        return [
            'total_sales' => 48250.75,
            'orders_today' => 127,
            'average_order' => 379.93,
            'active_customers' => 2847,
            'stock_value' => 385600,
            'low_stock_items' => 23,
            'pending_orders' => 35,
            'staff_online' => 42,
        ];
    }

    /**
     * Get mock chart data (sales over time)
     */
    public static function getSalesChart(): array
    {
        return [
            ['date' => 'Mon', 'sales' => 4200],
            ['date' => 'Tue', 'sales' => 5100],
            ['date' => 'Wed', 'sales' => 4800],
            ['date' => 'Thu', 'sales' => 6300],
            ['date' => 'Fri', 'sales' => 7200],
            ['date' => 'Sat', 'sales' => 8900],
            ['date' => 'Sun', 'sales' => 6400],
        ];
    }

    /**
     * Get mock activities
     */
    public static function getActivities(): array
    {
        return [
            ['icon' => '📦', 'text' => 'Order #1045 shipped to customer', 'time' => '2 mins ago'],
            ['icon' => '💰', 'text' => 'Payment of $149.99 received', 'time' => '5 mins ago'],
            ['icon' => '🔔', 'text' => 'Low stock alert: JUUL Pods (Mint)', 'time' => '12 mins ago'],
            ['icon' => '👤', 'text' => 'New customer registered: Alex Martin', 'time' => '18 mins ago'],
            ['icon' => '📊', 'text' => 'Daily report generated', 'time' => '25 mins ago'],
            ['icon' => '✅', 'text' => 'Stock count completed - Auckland CBD', 'time' => '1 hour ago'],
        ];
    }
}
