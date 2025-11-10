/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.5.29-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: jcepnzzkmj
-- ------------------------------------------------------
-- Server version	10.5.29-MariaDB-deb11-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `stock_transfers`
--

DROP TABLE IF EXISTS `stock_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_transfers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(20) NOT NULL,
  `type` enum('purchase_order_distribution','stock_transfer','juice_transfer','internal_transfer','peer_transfer','return_to_warehouse','excess_rebalance') NOT NULL,
  `source_outlet_id` varchar(50) NOT NULL,
  `source_outlet_name` varchar(200) DEFAULT NULL,
  `destination_outlet_id` varchar(50) NOT NULL,
  `destination_outlet_name` varchar(200) DEFAULT NULL,
  `is_juice_transfer` tinyint(1) DEFAULT 0,
  `packaging_type` enum('small_bag','box','pallet') DEFAULT NULL,
  `source_purchase_order_id` varchar(50) DEFAULT NULL,
  `source_consignment_id` varchar(50) DEFAULT NULL,
  `ai_suggested` tinyint(1) DEFAULT 0,
  `ai_confidence` decimal(4,3) DEFAULT NULL,
  `ai_reasoning` text DEFAULT NULL,
  `ai_alternative_routes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ai_alternative_routes`)),
  `requires_approval` tinyint(1) DEFAULT 1,
  `auto_approved` tinyint(1) DEFAULT 0,
  `was_modified` tinyint(1) DEFAULT 0,
  `modification_notes` text DEFAULT NULL,
  `status` enum('created','pending_approval','approved','picking','packing','labeled','shipped','in_transit','delivered','completed','cancelled') DEFAULT 'created',
  `priority` enum('critical','high','medium','low') DEFAULT 'medium',
  `reason` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `customer_facing_notes` text DEFAULT NULL,
  `scheduled_ship_date` date DEFAULT NULL,
  `actual_ship_date` date DEFAULT NULL,
  `estimated_delivery_date` date DEFAULT NULL,
  `actual_delivery_date` date DEFAULT NULL,
  `courier_name` varchar(100) DEFAULT NULL,
  `courier_service` varchar(100) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `tracking_url` text DEFAULT NULL,
  `label_pdf_url` text DEFAULT NULL,
  `freight_cost` decimal(10,2) DEFAULT 0.00,
  `packaging_cost` decimal(10,2) DEFAULT 0.00,
  `insurance_cost` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `cost_savings` decimal(10,2) DEFAULT 0.00,
  `total_items` int(11) DEFAULT 0,
  `total_value` decimal(10,2) DEFAULT 0.00,
  `total_margin` decimal(10,2) DEFAULT 0.00,
  `margin_after_freight` decimal(10,2) DEFAULT 0.00,
  `created_by` int(10) unsigned NOT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `picked_by` int(10) unsigned DEFAULT NULL,
  `packed_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfer_number` (`transfer_number`),
  KEY `idx_transfer_number` (`transfer_number`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_source` (`source_outlet_id`,`status`),
  KEY `idx_destination` (`destination_outlet_id`,`status`),
  KEY `idx_juice` (`is_juice_transfer`,`status`),
  KEY `idx_tracking` (`tracking_number`),
  KEY `idx_ai` (`ai_suggested`,`ai_confidence`),
  KEY `idx_dates` (`scheduled_ship_date`,`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_consignment` (`source_consignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stock_transfer_items`
--

DROP TABLE IF EXISTS `stock_transfer_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_transfer_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(10) unsigned NOT NULL,
  `product_id` varchar(50) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `quantity_picked` int(11) DEFAULT 0,
  `quantity_packed` int(11) DEFAULT 0,
  `quantity_delivered` int(11) DEFAULT 0,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `unit_sell` decimal(10,2) DEFAULT 0.00,
  `unit_margin` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `total_sell` decimal(10,2) DEFAULT 0.00,
  `total_margin` decimal(10,2) DEFAULT 0.00,
  `unit_weight_kg` decimal(6,3) DEFAULT 0.000,
  `total_weight_kg` decimal(8,3) DEFAULT 0.000,
  `status` enum('pending','picking','picked','packed','shipped','delivered') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transfer` (`transfer_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `stock_transfer_items_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `excess_stock_alerts`
--

DROP TABLE IF EXISTS `excess_stock_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `excess_stock_alerts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` varchar(50) NOT NULL,
  `outlet_name` varchar(200) DEFAULT NULL,
  `product_id` varchar(50) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `current_stock` int(11) NOT NULL,
  `weekly_sales_avg` decimal(10,2) DEFAULT 0.00,
  `weeks_of_stock` decimal(6,2) DEFAULT 0.00,
  `days_since_last_sale` int(11) DEFAULT 0,
  `severity` enum('caution','warning','critical') NOT NULL,
  `suggested_action` enum('peer_transfer','return_warehouse','wait_monitor','mark_clearance','no_action') NOT NULL,
  `suggested_destination_outlet_id` varchar(50) DEFAULT NULL,
  `suggested_destination_name` varchar(200) DEFAULT NULL,
  `suggested_quantity` int(11) DEFAULT NULL,
  `suggested_reason` text DEFAULT NULL,
  `ai_confidence` decimal(4,3) DEFAULT 0.000,
  `ai_reasoning` text DEFAULT NULL,
  `status` enum('new','reviewing','actioned','dismissed','expired') DEFAULT 'new',
  `transfer_id` int(10) unsigned DEFAULT NULL,
  `actioned_by` int(10) unsigned DEFAULT NULL,
  `actioned_at` timestamp NULL DEFAULT NULL,
  `action_notes` text DEFAULT NULL,
  `detected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transfer_id` (`transfer_id`),
  KEY `idx_outlet` (`outlet_id`,`status`),
  KEY `idx_product` (`product_id`),
  KEY `idx_severity` (`severity`,`status`),
  KEY `idx_status` (`status`),
  KEY `idx_suggested_action` (`suggested_action`,`status`),
  KEY `idx_detected` (`detected_at`),
  CONSTRAINT `excess_stock_alerts_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stock_velocity_tracking`
--

DROP TABLE IF EXISTS `stock_velocity_tracking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_velocity_tracking` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` varchar(50) NOT NULL,
  `product_id` varchar(50) NOT NULL,
  `sales_last_7_days` int(11) DEFAULT 0,
  `sales_last_30_days` int(11) DEFAULT 0,
  `sales_last_90_days` int(11) DEFAULT 0,
  `avg_weekly_sales` decimal(10,2) DEFAULT 0.00,
  `velocity` enum('fast','medium','slow','dead') NOT NULL,
  `current_stock` int(11) DEFAULT 0,
  `weeks_of_stock` decimal(6,2) DEFAULT 0.00,
  `trend` enum('increasing','stable','declining','unknown') DEFAULT 'unknown',
  `trend_percentage` decimal(6,2) DEFAULT 0.00,
  `predicted_stockout_date` date DEFAULT NULL,
  `stockout_risk` enum('none','low','medium','high','critical') DEFAULT 'none',
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_points_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_outlet_product` (`outlet_id`,`product_id`),
  KEY `idx_outlet_product` (`outlet_id`,`product_id`),
  KEY `idx_velocity` (`velocity`),
  KEY `idx_stockout_risk` (`stockout_risk`),
  KEY `idx_calculated` (`calculated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `freight_costs`
--

DROP TABLE IF EXISTS `freight_costs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `freight_costs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `courier_name` enum('nz_post','nz_couriers','aramex','mainfreight','peer_transfer','other') NOT NULL,
  `service_level` enum('standard','express','overnight','rural','economy') DEFAULT 'standard',
  `from_region` varchar(100) NOT NULL,
  `to_region` varchar(100) NOT NULL,
  `zone_code` varchar(20) DEFAULT NULL,
  `weight_min` decimal(6,2) DEFAULT 0.00,
  `weight_max` decimal(6,2) DEFAULT 999.99,
  `base_cost` decimal(10,2) NOT NULL,
  `cost_per_kg` decimal(10,2) DEFAULT 0.00,
  `rural_surcharge` decimal(10,2) DEFAULT 0.00,
  `signature_fee` decimal(10,2) DEFAULT 0.00,
  `insurance_percentage` decimal(5,2) DEFAULT 0.00,
  `fuel_surcharge_percentage` decimal(5,2) DEFAULT 0.00,
  `max_weight_kg` decimal(6,2) DEFAULT NULL,
  `max_length_cm` decimal(6,2) DEFAULT NULL,
  `max_girth_cm` decimal(6,2) DEFAULT NULL,
  `requires_account` tinyint(1) DEFAULT 0,
  `estimated_delivery_days` int(11) DEFAULT 3,
  `guaranteed_delivery` tinyint(1) DEFAULT 0,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `is_api_rate` tinyint(1) DEFAULT 0,
  `last_api_update` timestamp NULL DEFAULT NULL,
  `api_rate_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_courier` (`courier_name`,`from_region`,`to_region`),
  KEY `idx_weight` (`weight_min`,`weight_max`),
  KEY `idx_zone` (`zone_code`),
  KEY `idx_effective` (`effective_from`,`effective_to`),
  KEY `idx_api` (`is_api_rate`,`last_api_update`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `outlet_freight_zones`
--

DROP TABLE IF EXISTS `outlet_freight_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `outlet_freight_zones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` varchar(50) NOT NULL,
  `outlet_name` varchar(200) NOT NULL,
  `outlet_type` enum('warehouse','retail','juice_manufacturing','hybrid') DEFAULT 'retail',
  `address_line1` varchar(200) DEFAULT NULL,
  `address_line2` varchar(200) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `freight_zone` varchar(50) DEFAULT NULL,
  `courier_zone_nz_post` varchar(20) DEFAULT NULL,
  `courier_zone_nz_couriers` varchar(20) DEFAULT NULL,
  `courier_zone_aramex` varchar(20) DEFAULT NULL,
  `distance_from_primary_warehouse_km` decimal(8,2) DEFAULT 0.00,
  `distance_from_frankton_km` decimal(8,2) DEFAULT 0.00,
  `is_rural` tinyint(1) DEFAULT 0,
  `is_island` tinyint(1) DEFAULT 0,
  `requires_ferry` tinyint(1) DEFAULT 0,
  `restricted_access` tinyint(1) DEFAULT 0,
  `is_flagship` tinyint(1) DEFAULT 0,
  `is_hub_store` tinyint(1) DEFAULT 0,
  `can_manufacture_juice` tinyint(1) DEFAULT 0,
  `shipment_frequency_per_week` decimal(4,2) DEFAULT 0.00,
  `avg_shipment_value` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `outlet_id` (`outlet_id`),
  KEY `idx_outlet` (`outlet_id`),
  KEY `idx_zone` (`freight_zone`),
  KEY `idx_type` (`outlet_type`),
  KEY `idx_flagship` (`is_flagship`),
  KEY `idx_hub` (`is_hub_store`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transfer_routes`
--

DROP TABLE IF EXISTS `transfer_routes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transfer_routes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `source_outlet_id` varchar(50) NOT NULL,
  `destination_outlet_id` varchar(50) NOT NULL,
  `route_type` enum('direct','peer','hub','multi_hop') NOT NULL,
  `intermediate_stops` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`intermediate_stops`)),
  `total_distance_km` decimal(8,2) DEFAULT 0.00,
  `estimated_days` int(11) DEFAULT 3,
  `direct_freight_cost` decimal(10,2) DEFAULT 0.00,
  `optimized_freight_cost` decimal(10,2) DEFAULT 0.00,
  `cost_savings` decimal(10,2) DEFAULT 0.00,
  `savings_percentage` decimal(5,2) DEFAULT 0.00,
  `times_used` int(11) DEFAULT 0,
  `times_successful` int(11) DEFAULT 0,
  `times_failed` int(11) DEFAULT 0,
  `success_rate` decimal(5,2) DEFAULT 0.00,
  `avg_delivery_time_days` decimal(4,2) DEFAULT 0.00,
  `first_used_at` timestamp NULL DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_route` (`source_outlet_id`,`destination_outlet_id`,`route_type`),
  KEY `idx_route` (`source_outlet_id`,`destination_outlet_id`),
  KEY `idx_savings` (`savings_percentage`),
  KEY `idx_performance` (`success_rate`,`times_used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transfer_boxes`
--

DROP TABLE IF EXISTS `transfer_boxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transfer_boxes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(10) unsigned NOT NULL,
  `box_number` int(11) NOT NULL,
  `box_type` enum('small','medium','large','pallet','bag') DEFAULT 'medium',
  `length_cm` decimal(6,2) DEFAULT NULL,
  `width_cm` decimal(6,2) DEFAULT NULL,
  `height_cm` decimal(6,2) DEFAULT NULL,
  `actual_weight_kg` decimal(8,3) DEFAULT 0.000,
  `volumetric_weight_kg` decimal(8,3) DEFAULT 0.000,
  `chargeable_weight_kg` decimal(8,3) DEFAULT 0.000,
  `items_count` int(11) DEFAULT 0,
  `items_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`items_json`)),
  `tracking_number` varchar(100) DEFAULT NULL,
  `label_url` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transfer` (`transfer_id`),
  KEY `idx_tracking` (`tracking_number`),
  CONSTRAINT `transfer_boxes_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transfer_rejections`
--

DROP TABLE IF EXISTS `transfer_rejections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transfer_rejections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` varchar(50) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `from_outlet_id` varchar(50) NOT NULL,
  `to_outlet_id` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `rejection_reason` enum('freight_exceeds_margin','margin_below_threshold','low_value_high_freight','margin_erosion_excessive','total_value_too_low','no_stock_available','destination_overstocked','other') NOT NULL,
  `rejection_details` text DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `unit_sell` decimal(10,2) DEFAULT NULL,
  `unit_margin` decimal(10,2) DEFAULT NULL,
  `freight_cost` decimal(10,2) DEFAULT NULL,
  `margin_after_freight` decimal(10,2) DEFAULT NULL,
  `margin_percentage` decimal(5,2) DEFAULT NULL,
  `alternative_suggested` enum('batch_wait','peer_transfer','hub_route','none') DEFAULT NULL,
  `alternative_details` text DEFAULT NULL,
  `rejected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rejected_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_route` (`from_outlet_id`,`to_outlet_id`),
  KEY `idx_reason` (`rejection_reason`),
  KEY `idx_rejected` (`rejected_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transfer_tracking_events`
--

DROP TABLE IF EXISTS `transfer_tracking_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transfer_tracking_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(10) unsigned NOT NULL,
  `event_type` enum('created','approved','picked','packed','labeled','collected','in_transit','out_for_delivery','delivered','failed_delivery','returned','exception') NOT NULL,
  `event_status` varchar(100) DEFAULT NULL,
  `event_description` text DEFAULT NULL,
  `event_location` varchar(255) DEFAULT NULL,
  `courier_status_code` varchar(50) DEFAULT NULL,
  `courier_raw_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`courier_raw_data`)),
  `event_timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transfer` (`transfer_id`,`event_timestamp`),
  KEY `idx_type` (`event_type`),
  KEY `idx_timestamp` (`event_timestamp`),
  CONSTRAINT `transfer_tracking_events_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-06 19:43:41
