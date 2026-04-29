/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `addendums`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `addendums` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `addendums_contract_id_foreign` (`contract_id`),
  CONSTRAINT `addendums_contract_id_foreign` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `area_headquarter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `area_headquarter` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `area_id` bigint(20) unsigned DEFAULT NULL,
  `headquarter_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `area_headquarter_area_id_foreign` (`area_id`),
  KEY `area_headquarter_headquarter_id_foreign` (`headquarter_id`),
  CONSTRAINT `area_headquarter_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `area_headquarter_headquarter_id_foreign` FOREIGN KEY (`headquarter_id`) REFERENCES `headquarters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `area_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `area_role` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `area_id` bigint(20) unsigned DEFAULT NULL,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `area_role_area_id_foreign` (`area_id`),
  KEY `area_role_role_id_foreign` (`role_id`),
  CONSTRAINT `area_role_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `area_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `areas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `business_service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `business_service` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` bigint(20) unsigned DEFAULT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `business_service_business_id_foreign` (`business_id`),
  KEY `business_service_service_id_foreign` (`service_id`),
  CONSTRAINT `business_service_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `business_service_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `businessables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `businessables` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `businessable_type` varchar(255) NOT NULL,
  `businessable_id` bigint(20) unsigned NOT NULL,
  `business_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `businessables_businessable_type_businessable_id_index` (`businessable_type`,`businessable_id`),
  KEY `businessables_business_id_foreign` (`business_id`),
  CONSTRAINT `businessables_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `businesses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `businesses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `ruc` varchar(255) DEFAULT NULL,
  `fiscal_address` varchar(255) DEFAULT NULL,
  `legal_address` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cafe_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cafe_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cafe_id` bigint(20) unsigned DEFAULT NULL,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cafe_roles_cafe_id_foreign` (`cafe_id`),
  KEY `cafe_roles_role_id_foreign` (`role_id`),
  CONSTRAINT `cafe_roles_cafe_id_foreign` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cafe_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cafe_service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cafe_service` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `cafe_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cafe_service_service_id_foreign` (`service_id`),
  KEY `cafe_service_cafe_id_foreign` (`cafe_id`),
  CONSTRAINT `cafe_service_cafe_id_foreign` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cafe_service_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cafe_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cafe_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cafe_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cafe_user_cafe_id_foreign` (`cafe_id`),
  KEY `cafe_user_user_id_foreign` (`user_id`),
  CONSTRAINT `cafe_user_cafe_id_foreign` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cafe_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cafes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cafes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `unit_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cafes_unit_id_foreign` (`unit_id`),
  CONSTRAINT `cafes_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `calories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dish_ingredient_level_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(10,4) DEFAULT NULL,
  `unit_measurement_id` bigint(20) unsigned DEFAULT NULL,
  `percentage` decimal(10,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `calories_dish_ingredient_level_id_foreign` (`dish_ingredient_level_id`),
  KEY `calories_unit_measurement_id_foreign` (`unit_measurement_id`),
  CONSTRAINT `calories_dish_ingredient_level_id_foreign` FOREIGN KEY (`dish_ingredient_level_id`) REFERENCES `dish_ingredient_levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `calories_unit_measurement_id_foreign` FOREIGN KEY (`unit_measurement_id`) REFERENCES `measurement_units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `category_epps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category_epps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `city_provider`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `city_provider` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `city_id` bigint(20) unsigned DEFAULT NULL,
  `provider_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `city_provider_city_id_foreign` (`city_id`),
  KEY `city_provider_provider_id_foreign` (`provider_id`),
  CONSTRAINT `city_provider_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `city_provider_provider_id_foreign` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cloth_cloth_provider`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cloth_cloth_provider` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cloth_id` bigint(20) unsigned NOT NULL,
  `cloth_provider_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cloth_cloth_provider_cloth_id_foreign` (`cloth_id`),
  KEY `cloth_cloth_provider_cloth_provider_id_foreign` (`cloth_provider_id`),
  CONSTRAINT `cloth_cloth_provider_cloth_id_foreign` FOREIGN KEY (`cloth_id`) REFERENCES `cloths` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cloth_cloth_provider_cloth_provider_id_foreign` FOREIGN KEY (`cloth_provider_id`) REFERENCES `cloth_providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cloth_inventories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cloth_inventories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cloth_id` bigint(20) unsigned NOT NULL,
  `color_id` bigint(20) unsigned NOT NULL,
  `cafe_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cloth_inventories_cloth_id_foreign` (`cloth_id`),
  KEY `cloth_inventories_color_id_foreign` (`color_id`),
  KEY `cloth_inventories_cafe_id_foreign` (`cafe_id`),
  CONSTRAINT `cloth_inventories_cafe_id_foreign` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cloth_inventories_cloth_id_foreign` FOREIGN KEY (`cloth_id`) REFERENCES `cloths` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cloth_inventories_color_id_foreign` FOREIGN KEY (`color_id`) REFERENCES `colors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cloth_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cloth_invoice_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cloth_invoice_id` bigint(20) unsigned NOT NULL,
  `cloth_id` bigint(20) unsigned DEFAULT NULL,
  `epp_id` bigint(20) unsigned DEFAULT NULL,
  `color_id` bigint(20) unsigned DEFAULT NULL,
  `size` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `total_price` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cloth_invoice_items_cloth_invoice_id_foreign` (`cloth_invoice_id`),
  KEY `cloth_invoice_items_cloth_id_foreign` (`cloth_id`),
  KEY `cloth_invoice_items_color_id_foreign` (`color_id`),
  KEY `cloth_invoice_items_epp_id_foreign` (`epp_id`),
  CONSTRAINT `cloth_invoice_items_cloth_id_foreign` FOREIGN KEY (`cloth_id`) REFERENCES `cloths` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cloth_invoice_items_cloth_invoice_id_foreign` FOREIGN KEY (`cloth_invoice_id`) REFERENCES `cloth_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cloth_invoice_items_color_id_foreign` FOREIGN KEY (`color_id`) REFERENCES `colors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cloth_invoice_items_epp_id_foreign` FOREIGN KEY (`epp_id`) REFERENCES `epps` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cloth_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cloth_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(255) DEFAULT NULL,
  `business_id` bigint(20) unsigned NOT NULL,
  `headquarter_id` bigint(20) unsigned DEFAULT NULL,
  `cloth_provider_id` bigint(20) unsigned NOT NULL,
  `date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `invoice_image` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cloth_invoices_business_id_foreign` (`business_id`),
  KEY `cloth_invoices_headquarter_id_foreign` (`headquarter_id`),
  KEY `cloth_invoices_cloth_provider_id_foreign` (`cloth_provider_id`),
  KEY `cloth_invoices_user_id_foreign` (`user_id`),
  CONSTRAINT `cloth_invoices_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cloth_invoices_cloth_provider_id_foreign` FOREIGN KEY (`cloth_provider_id`) REFERENCES `cloth_providers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cloth_invoices_headquarter_id_foreign` FOREIGN KEY (`headquarter_id`) REFERENCES `headquarters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cloth_invoices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cloth_provider_epp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cloth_provider_epp` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cloth_provider_id` bigint(20) unsigned NOT NULL,
  `epp_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cloth_provider_epp_cloth_provider_id_foreign` (`cloth_provider_id`),
  KEY `cloth_provider_epp_epp_id_foreign` (`epp_id`),
  CONSTRAINT `cloth_provider_epp_cloth_provider_id_foreign` FOREIGN KEY (`cloth_provider_id`) REFERENCES `cloth_providers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cloth_provider_epp_epp_id_foreign` FOREIGN KEY (`epp_id`) REFERENCES `epps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cloth_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cloth_providers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cloth_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cloth_role` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cloth_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `cafe_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cloth_role_cloth_id_foreign` (`cloth_id`),
  KEY `cloth_role_role_id_foreign` (`role_id`),
  KEY `cloth_role_cafe_id_foreign` (`cafe_id`),
  CONSTRAINT `cloth_role_cafe_id_foreign` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cloth_role_cloth_id_foreign` FOREIGN KEY (`cloth_id`) REFERENCES `cloths` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cloth_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cloths`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cloths` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `colors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `colors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `hex_code` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `colors_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `computer_equipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `computer_equipments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `presentation` varchar(255) DEFAULT NULL,
  `color` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contracts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dealership_id` bigint(20) unsigned DEFAULT NULL,
  `business_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contracts_dealership_id_foreign` (`dealership_id`),
  KEY `contracts_business_id_foreign` (`business_id`),
  CONSTRAINT `contracts_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contracts_dealership_id_foreign` FOREIGN KEY (`dealership_id`) REFERENCES `dealerships` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `daily_menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_menus` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `daily_portions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_portions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `weekly_program_id` bigint(20) unsigned NOT NULL,
  `date` date NOT NULL,
  `meal_type` varchar(255) NOT NULL,
  `portions_count` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `daily_portions_weekly_program_id_foreign` (`weekly_program_id`),
  CONSTRAINT `daily_portions_weekly_program_id_foreign` FOREIGN KEY (`weekly_program_id`) REFERENCES `weekly_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dealerships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dealerships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `ruc` varchar(255) DEFAULT NULL,
  `fiscal_address` varchar(255) DEFAULT NULL,
  `legal_address` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dinners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dinners` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `dni` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `subdealership_id` bigint(20) unsigned DEFAULT NULL,
  `cafe_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dinners_subdealership_id_foreign` (`subdealership_id`),
  KEY `dinners_cafe_id_foreign` (`cafe_id`),
  CONSTRAINT `dinners_cafe_id_foreign` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dinners_subdealership_id_foreign` FOREIGN KEY (`subdealership_id`) REFERENCES `subdealerships` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dish_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dish_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `mesearument_unit` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dish_category_dish`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dish_category_dish` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dish_category_id` bigint(20) unsigned DEFAULT NULL,
  `dish_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dish_category_dish_dish_category_id_foreign` (`dish_category_id`),
  KEY `dish_category_dish_dish_id_foreign` (`dish_id`),
  CONSTRAINT `dish_category_dish_dish_category_id_foreign` FOREIGN KEY (`dish_category_id`) REFERENCES `dish_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dish_category_dish_dish_id_foreign` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dish_category_serviceables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dish_category_serviceables` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dish_category_id` bigint(20) unsigned DEFAULT NULL,
  `serviceable_id` bigint(20) unsigned DEFAULT NULL,
  `serving_amount` decimal(10,2) DEFAULT NULL,
  `measurement_unit_id` bigint(20) unsigned DEFAULT NULL,
  `serving_percentaje` decimal(10,2) DEFAULT NULL,
  `lower_limit_cost` decimal(10,2) DEFAULT NULL,
  `total_lower_limit_cost` decimal(10,2) DEFAULT NULL,
  `upper_limit_cost` decimal(10,2) DEFAULT NULL,
  `total_upper_limit_cost` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dish_category_serviceables_dish_category_id_foreign` (`dish_category_id`),
  KEY `dish_category_serviceables_serviceable_id_foreign` (`serviceable_id`),
  KEY `dish_category_serviceables_measurement_unit_id_foreign` (`measurement_unit_id`),
  CONSTRAINT `dish_category_serviceables_dish_category_id_foreign` FOREIGN KEY (`dish_category_id`) REFERENCES `dish_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dish_category_serviceables_measurement_unit_id_foreign` FOREIGN KEY (`measurement_unit_id`) REFERENCES `measurement_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dish_category_serviceables_serviceable_id_foreign` FOREIGN KEY (`serviceable_id`) REFERENCES `serviceables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dish_ingredient`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dish_ingredient` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dish_id` bigint(20) unsigned DEFAULT NULL,
  `ingredient_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dish_ingredient_dish_id_foreign` (`dish_id`),
  KEY `dish_ingredient_ingredient_id_foreign` (`ingredient_id`),
  CONSTRAINT `dish_ingredient_dish_id_foreign` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dish_ingredient_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dish_ingredient_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dish_ingredient_levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dish_id` bigint(20) unsigned DEFAULT NULL,
  `ingredient_id` bigint(20) unsigned DEFAULT NULL,
  `level_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dish_ingredient_levels_dish_id_foreign` (`dish_id`),
  KEY `dish_ingredient_levels_ingredient_id_foreign` (`ingredient_id`),
  KEY `dish_ingredient_levels_level_id_foreign` (`level_id`),
  CONSTRAINT `dish_ingredient_levels_dish_id_foreign` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dish_ingredient_levels_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dish_ingredient_levels_level_id_foreign` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dish_recipe_ingredients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dish_recipe_ingredients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dish_recipe_id` bigint(20) unsigned NOT NULL,
  `ingredient_id` bigint(20) unsigned NOT NULL,
  `gross_weight` decimal(10,2) NOT NULL DEFAULT 0.00,
  `solid_waste` decimal(10,2) NOT NULL DEFAULT 0.00,
  `liquid_waste` decimal(10,2) NOT NULL DEFAULT 0.00,
  `calories` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `unit_price` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `net_weight` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dish_recipe_ingredients_dish_recipe_id_foreign` (`dish_recipe_id`),
  KEY `dish_recipe_ingredients_ingredient_id_foreign` (`ingredient_id`),
  CONSTRAINT `dish_recipe_ingredients_dish_recipe_id_foreign` FOREIGN KEY (`dish_recipe_id`) REFERENCES `dish_recipes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dish_recipe_ingredients_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dish_recipe_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dish_recipe_levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dish_recipe_id` bigint(20) unsigned NOT NULL,
  `level_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dish_recipe_levels_dish_recipe_id_foreign` (`dish_recipe_id`),
  KEY `dish_recipe_levels_level_id_foreign` (`level_id`),
  CONSTRAINT `dish_recipe_levels_dish_recipe_id_foreign` FOREIGN KEY (`dish_recipe_id`) REFERENCES `dish_recipes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dish_recipe_levels_level_id_foreign` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dish_recipes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dish_recipes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dish_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT 'Receta Estándar',
  `total_gross_weight` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_waste_weight` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_calories` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `total_net_weight` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dish_recipes_dish_id_foreign` (`dish_id`),
  CONSTRAINT `dish_recipes_dish_id_foreign` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dishes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dishes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dishes_user_id_foreign` (`user_id`),
  CONSTRAINT `dishes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dishes_test`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dishes_test` (
  `id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `dish_category_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dosifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dosifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ingredient_id` bigint(20) unsigned NOT NULL,
  `energy` decimal(11,2) DEFAULT NULL,
  `water` decimal(11,2) DEFAULT NULL,
  `protein` decimal(11,2) DEFAULT NULL,
  `lipid` decimal(11,2) DEFAULT NULL,
  `carbohydrate` decimal(11,2) DEFAULT NULL,
  `fiber` decimal(11,2) DEFAULT NULL,
  `ash` decimal(11,2) DEFAULT NULL,
  `calcium` decimal(11,2) DEFAULT NULL,
  `phosphorus` decimal(11,2) DEFAULT NULL,
  `iron` decimal(11,2) DEFAULT NULL,
  `retinol` decimal(11,2) DEFAULT NULL,
  `thiamine` decimal(11,2) DEFAULT NULL,
  `riboflavin` decimal(11,2) DEFAULT NULL,
  `niacin` decimal(11,2) DEFAULT NULL,
  `a_asc` decimal(11,2) DEFAULT NULL,
  `sodium` decimal(11,2) DEFAULT NULL,
  `potassium` decimal(11,2) DEFAULT NULL,
  `magnesium` decimal(11,2) DEFAULT NULL,
  `zinc` decimal(11,2) DEFAULT NULL,
  `selenium` decimal(11,2) DEFAULT NULL,
  `a_folic` decimal(11,2) DEFAULT NULL,
  `v_b6` decimal(11,2) DEFAULT NULL,
  `v_e` decimal(11,2) DEFAULT NULL,
  `v_b12` decimal(11,2) DEFAULT NULL,
  `v_b9` decimal(11,2) DEFAULT NULL,
  `iodine` decimal(11,2) DEFAULT NULL,
  `cholesterol` decimal(11,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dosifications_ingredient_id_foreign` (`ingredient_id`),
  CONSTRAINT `dosifications_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `epp_city_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `epp_city_providers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `epp_id` bigint(20) unsigned NOT NULL,
  `city_id` bigint(20) unsigned NOT NULL,
  `cloth_provider_id` bigint(20) unsigned NOT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `epp_city_providers_epp_id_foreign` (`epp_id`),
  KEY `epp_city_providers_city_id_foreign` (`city_id`),
  KEY `epp_city_providers_cloth_provider_id_foreign` (`cloth_provider_id`),
  CONSTRAINT `epp_city_providers_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `epp_city_providers_cloth_provider_id_foreign` FOREIGN KEY (`cloth_provider_id`) REFERENCES `cloth_providers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `epp_city_providers_epp_id_foreign` FOREIGN KEY (`epp_id`) REFERENCES `epps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `epp_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `epp_role` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `epp_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `cafe_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `color_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `epp_role_epp_id_foreign` (`epp_id`),
  KEY `epp_role_role_id_foreign` (`role_id`),
  KEY `epp_role_cafe_id_foreign` (`cafe_id`),
  KEY `epp_role_color_id_foreign` (`color_id`),
  CONSTRAINT `epp_role_cafe_id_foreign` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `epp_role_color_id_foreign` FOREIGN KEY (`color_id`) REFERENCES `colors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `epp_role_epp_id_foreign` FOREIGN KEY (`epp_id`) REFERENCES `epps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `epp_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `epp_size_pivot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `epp_size_pivot` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `epp_id` bigint(20) unsigned NOT NULL,
  `size_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `epp_size_pivot_epp_id_foreign` (`epp_id`),
  KEY `epp_size_pivot_size_id_foreign` (`size_id`),
  CONSTRAINT `epp_size_pivot_epp_id_foreign` FOREIGN KEY (`epp_id`) REFERENCES `epps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `epp_size_pivot_size_id_foreign` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `epp_sizes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `epp_sizes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `epp_id` bigint(20) unsigned NOT NULL,
  `size` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `epp_sizes_epp_id_foreign` (`epp_id`),
  CONSTRAINT `epp_sizes_epp_id_foreign` FOREIGN KEY (`epp_id`) REFERENCES `epps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `epps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `epps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_epp_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `epps_category_epp_id_foreign` (`category_epp_id`),
  CONSTRAINT `epps_category_epp_id_foreign` FOREIGN KEY (`category_epp_id`) REFERENCES `category_epps` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gross_weights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gross_weights` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dish_ingredient_level_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(10,4) DEFAULT NULL,
  `unit_measurement_id` bigint(20) unsigned DEFAULT NULL,
  `percentage` decimal(10,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gross_weights_dish_ingredient_level_id_foreign` (`dish_ingredient_level_id`),
  KEY `gross_weights_unit_measurement_id_foreign` (`unit_measurement_id`),
  CONSTRAINT `gross_weights_dish_ingredient_level_id_foreign` FOREIGN KEY (`dish_ingredient_level_id`) REFERENCES `dish_ingredient_levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gross_weights_unit_measurement_id_foreign` FOREIGN KEY (`unit_measurement_id`) REFERENCES `measurement_units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `guard_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guard_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `guard_id` bigint(20) unsigned DEFAULT NULL,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `staff_id` bigint(20) unsigned DEFAULT NULL,
  `replacement_id` bigint(20) unsigned DEFAULT NULL,
  `observation` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `guard_roles_guard_id_foreign` (`guard_id`),
  KEY `guard_roles_role_id_foreign` (`role_id`),
  KEY `guard_roles_staff_id_foreign` (`staff_id`),
  CONSTRAINT `guard_roles_guard_id_foreign` FOREIGN KEY (`guard_id`) REFERENCES `guards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `guard_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `guard_roles_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `guards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cafe_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `guards_cafe_id_foreign` (`cafe_id`),
  CONSTRAINT `guards_cafe_id_foreign` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `headquarters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `headquarters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `business_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `headquarters_business_id_foreign` (`business_id`),
  CONSTRAINT `headquarters_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ingredient_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ingredient_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ingredient_city_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ingredient_city_providers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ingredient_id` bigint(20) unsigned DEFAULT NULL,
  `provider_id` bigint(20) unsigned DEFAULT NULL,
  `city_id` bigint(20) unsigned DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ingredient_city_provider_ingredient_id_foreign` (`ingredient_id`),
  KEY `ingredient_city_provider_provider_id_foreign` (`provider_id`),
  KEY `ingredient_city_provider_city_id_foreign` (`city_id`),
  CONSTRAINT `ingredient_city_provider_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ingredient_city_provider_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ingredient_city_provider_provider_id_foreign` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ingredient_costs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ingredient_costs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dish_ingredient_level_id` bigint(20) unsigned DEFAULT NULL,
  `base_cost` decimal(10,4) DEFAULT NULL,
  `cost_percentage` decimal(10,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ingredient_costs_dish_ingredient_level_id_foreign` (`dish_ingredient_level_id`),
  CONSTRAINT `ingredient_costs_dish_ingredient_level_id_foreign` FOREIGN KEY (`dish_ingredient_level_id`) REFERENCES `dish_ingredient_levels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ingredients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ingredients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `presentation` varchar(255) DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `waste` double DEFAULT NULL,
  `energy` double DEFAULT NULL,
  `ingredient_category_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `measurement_unit_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ingredients_ingredient_category_id_foreign` (`ingredient_category_id`),
  KEY `ingredients_measurement_unit_id_foreign` (`measurement_unit_id`),
  CONSTRAINT `ingredients_ingredient_category_id_foreign` FOREIGN KEY (`ingredient_category_id`) REFERENCES `ingredient_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `ingredients_measurement_unit_id_foreign` FOREIGN KEY (`measurement_unit_id`) REFERENCES `measurement_units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_stocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_stocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stockable_type` varchar(255) NOT NULL,
  `stockable_id` bigint(20) unsigned NOT NULL,
  `headquarter_id` bigint(20) unsigned DEFAULT NULL,
  `cafe_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `size` varchar(255) DEFAULT NULL,
  `color_id` bigint(20) unsigned DEFAULT NULL,
  `condition` enum('Nuevo','En Almacén') NOT NULL DEFAULT 'Nuevo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `unit_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_stocks_stockable_type_stockable_id_index` (`stockable_type`,`stockable_id`),
  KEY `inventory_stocks_headquarter_id_foreign` (`headquarter_id`),
  KEY `inventory_stocks_cafe_id_foreign` (`cafe_id`),
  KEY `inventory_stocks_unit_id_foreign` (`unit_id`),
  KEY `inventory_stocks_color_id_foreign` (`color_id`),
  CONSTRAINT `inventory_stocks_cafe_id_foreign` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_stocks_color_id_foreign` FOREIGN KEY (`color_id`) REFERENCES `colors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_stocks_headquarter_id_foreign` FOREIGN KEY (`headquarter_id`) REFERENCES `headquarters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_stocks_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_transfer_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_transfer_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inventory_transfer_id` bigint(20) unsigned NOT NULL,
  `stockable_type` varchar(255) NOT NULL,
  `stockable_id` bigint(20) unsigned NOT NULL,
  `quantity` int(11) NOT NULL,
  `size` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `color_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_transfer_items_inventory_transfer_id_foreign` (`inventory_transfer_id`),
  KEY `inventory_transfer_items_stockable_type_stockable_id_index` (`stockable_type`,`stockable_id`),
  KEY `inventory_transfer_items_color_id_foreign` (`color_id`),
  CONSTRAINT `inventory_transfer_items_color_id_foreign` FOREIGN KEY (`color_id`) REFERENCES `colors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_transfer_items_inventory_transfer_id_foreign` FOREIGN KEY (`inventory_transfer_id`) REFERENCES `inventory_transfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` bigint(20) unsigned DEFAULT NULL,
  `unit_id` bigint(20) unsigned NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `returned_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'sent',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_transfers_staff_id_foreign` (`staff_id`),
  KEY `inventory_transfers_unit_id_foreign` (`unit_id`),
  CONSTRAINT `inventory_transfers_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_transfers_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kitchen_equipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kitchen_equipments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `brand` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL,
  `size` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `color` varchar(255) DEFAULT NULL,
  `current_type` varchar(255) DEFAULT NULL,
  `series` varchar(255) DEFAULT NULL,
  `manual` text DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `liquid_wastes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `liquid_wastes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dish_ingredient_level_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(10,4) DEFAULT NULL,
  `unit_measurement_id` bigint(20) unsigned DEFAULT NULL,
  `percentage` decimal(10,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `liquid_wastes_dish_ingredient_level_id_foreign` (`dish_ingredient_level_id`),
  KEY `liquid_wastes_unit_measurement_id_foreign` (`unit_measurement_id`),
  CONSTRAINT `liquid_wastes_dish_ingredient_level_id_foreign` FOREIGN KEY (`dish_ingredient_level_id`) REFERENCES `dish_ingredient_levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `liquid_wastes_unit_measurement_id_foreign` FOREIGN KEY (`unit_measurement_id`) REFERENCES `measurement_units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `measurement_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `measurement_units` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `abbreviation` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menu_cycles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menu_cycles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menu_structures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menu_structures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `meal_type` varchar(255) NOT NULL,
  `dish_category_id` bigint(20) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `cost_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `menu_structures_dish_category_id_foreign` (`dish_category_id`),
  CONSTRAINT `menu_structures_dish_category_id_foreign` FOREIGN KEY (`dish_category_id`) REFERENCES `dish_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mine_subdealerships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mine_subdealerships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mine_id` bigint(20) unsigned NOT NULL,
  `subdealership_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mine_subdealerships_mine_id_foreign` (`mine_id`),
  KEY `mine_subdealerships_subdealership_id_foreign` (`subdealership_id`),
  CONSTRAINT `mine_subdealerships_mine_id_foreign` FOREIGN KEY (`mine_id`) REFERENCES `mines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mine_subdealerships_subdealership_id_foreign` FOREIGN KEY (`subdealership_id`) REFERENCES `subdealerships` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `dealership_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mines_dealership_id_foreign` (`dealership_id`),
  CONSTRAINT `mines_dealership_id_foreign` FOREIGN KEY (`dealership_id`) REFERENCES `dealerships` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `net_weights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `net_weights` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dish_ingredient_level_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(10,4) DEFAULT NULL,
  `unit_measurement_id` bigint(20) unsigned DEFAULT NULL,
  `percentage` decimal(10,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `net_weights_dish_ingredient_level_id_foreign` (`dish_ingredient_level_id`),
  KEY `net_weights_unit_measurement_id_foreign` (`unit_measurement_id`),
  CONSTRAINT `net_weights_dish_ingredient_level_id_foreign` FOREIGN KEY (`dish_ingredient_level_id`) REFERENCES `dish_ingredient_levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `net_weights_unit_measurement_id_foreign` FOREIGN KEY (`unit_measurement_id`) REFERENCES `measurement_units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nutritional_factors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nutritional_factors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ingredient_id` bigint(20) unsigned NOT NULL,
  `nfactorcal` decimal(11,2) DEFAULT NULL,
  `composition` decimal(11,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nutritional_factors_ingredient_id_foreign` (`ingredient_id`),
  CONSTRAINT `nutritional_factors_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `observations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `observations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `observation` text NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `observations_staff_id_foreign` (`staff_id`),
  KEY `observations_user_id_foreign` (`user_id`),
  CONSTRAINT `observations_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `observations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_methods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `period_staffs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `period_staffs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `period_id` bigint(20) unsigned DEFAULT NULL,
  `staff_id` bigint(20) unsigned DEFAULT NULL,
  `status` tinyint(4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `period_staffs_period_id_foreign` (`period_id`),
  KEY `period_staffs_staff_id_foreign` (`staff_id`),
  CONSTRAINT `period_staffs_period_id_foreign` FOREIGN KEY (`period_id`) REFERENCES `periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `period_staffs_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cafe_id` bigint(20) unsigned DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `periods_cafe_id_foreign` (`cafe_id`),
  CONSTRAINT `periods_cafe_id_foreign` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sidebar_name` text DEFAULT NULL,
  `route_name` text DEFAULT NULL,
  `icon_class` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `providers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'ingredient',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint(20) unsigned NOT NULL,
  `ingredient_id` bigint(20) unsigned NOT NULL,
  `total_amount` decimal(14,4) NOT NULL,
  `unit` varchar(255) NOT NULL,
  `estimated_cost` decimal(14,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_order_items_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `purchase_order_items_ingredient_id_foreign` (`ingredient_id`),
  CONSTRAINT `purchase_order_items_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  CONSTRAINT `purchase_order_items_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `weekly_program_id` bigint(20) unsigned NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pendiente',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_orders_weekly_program_id_foreign` (`weekly_program_id`),
  CONSTRAINT `purchase_orders_weekly_program_id_foreign` FOREIGN KEY (`weekly_program_id`) REFERENCES `weekly_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `receipt_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receipt_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recipes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recipes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dish_id` bigint(20) unsigned NOT NULL,
  `ingredient_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(8,4) NOT NULL,
  `unit_id` bigint(20) unsigned NOT NULL,
  `yield_factor` decimal(5,2) NOT NULL DEFAULT 1.00,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recipes_dish_id_ingredient_id_unique` (`dish_id`,`ingredient_id`),
  KEY `recipes_ingredient_id_foreign` (`ingredient_id`),
  KEY `recipes_unit_id_foreign` (`unit_id`),
  CONSTRAINT `recipes_dish_id_foreign` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recipes_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recipes_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `measurement_units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `area_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`),
  KEY `roles_area_id_foreign` (`area_id`),
  CONSTRAINT `roles_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) unsigned DEFAULT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `service_name` varchar(255) DEFAULT NULL,
  `amount` int(11) DEFAULT NULL,
  `um` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit_value` decimal(12,4) DEFAULT NULL,
  `unit_price` decimal(12,4) DEFAULT NULL,
  `sale_value` decimal(12,4) DEFAULT NULL,
  `igv` decimal(12,4) DEFAULT NULL,
  `total` decimal(12,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_details_sale_id_foreign` (`sale_id`),
  KEY `sale_details_service_id_foreign` (`service_id`),
  CONSTRAINT `sale_details_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_details_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dinner_id` bigint(20) unsigned DEFAULT NULL,
  `cafe_id` bigint(20) unsigned DEFAULT NULL,
  `date` date DEFAULT NULL,
  `sale_type_id` bigint(20) unsigned DEFAULT NULL,
  `payment_method_id` bigint(20) unsigned DEFAULT NULL,
  `business_id` bigint(20) unsigned DEFAULT NULL,
  `total_discounts` decimal(12,4) DEFAULT 0.0000,
  `total_non_taxable_operations` decimal(12,4) DEFAULT 0.0000,
  `total_taxable_operations` decimal(12,4) DEFAULT 0.0000,
  `total_unaffected_operations` decimal(12,4) DEFAULT 0.0000,
  `total_exonerated_operations` decimal(12,4) DEFAULT 0.0000,
  `total_exported_operations` decimal(12,4) DEFAULT 0.0000,
  `total_igv` decimal(12,4) DEFAULT 0.0000,
  `total_isc` decimal(12,4) DEFAULT 0.0000,
  `total_other_taxes` decimal(12,4) DEFAULT 0.0000,
  `total_other_charges` decimal(12,4) DEFAULT 0.0000,
  `total` decimal(12,4) DEFAULT 0.0000,
  `status` tinyint(4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_cafe_id_foreign` (`cafe_id`),
  KEY `sales_sale_type_id_foreign` (`sale_type_id`),
  KEY `sales_payment_method_id_foreign` (`payment_method_id`),
  KEY `sales_business_id_foreign` (`business_id`),
  KEY `sales_dinner_id_foreign` (`dinner_id`),
  CONSTRAINT `sales_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_cafe_id_foreign` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_dinner_id_foreign` FOREIGN KEY (`dinner_id`) REFERENCES `dinners` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_sale_type_id_foreign` FOREIGN KEY (`sale_type_id`) REFERENCES `sale_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `service_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `serviceables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `serviceables` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `serviceable_type` varchar(255) NOT NULL,
  `serviceable_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `serviceables_serviceable_type_serviceable_id_index` (`serviceable_type`,`serviceable_id`),
  KEY `serviceables_service_id_foreign` (`service_id`),
  CONSTRAINT `serviceables_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `type` tinyint(4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sizes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sizes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sizes_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `solid_wastes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `solid_wastes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dish_ingredient_level_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(10,4) DEFAULT NULL,
  `unit_measurement_id` bigint(20) unsigned DEFAULT NULL,
  `percentage` decimal(10,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `solid_wastes_dish_ingredient_level_id_foreign` (`dish_ingredient_level_id`),
  KEY `solid_wastes_unit_measurement_id_foreign` (`unit_measurement_id`),
  CONSTRAINT `solid_wastes_dish_ingredient_level_id_foreign` FOREIGN KEY (`dish_ingredient_level_id`) REFERENCES `dish_ingredient_levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `solid_wastes_unit_measurement_id_foreign` FOREIGN KEY (`unit_measurement_id`) REFERENCES `measurement_units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `dni` varchar(255) NOT NULL,
  `cell` varchar(255) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `sex` tinyint(1) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `civilstatus` tinyint(4) DEFAULT NULL,
  `contactname` varchar(255) DEFAULT NULL,
  `contactcell` varchar(255) DEFAULT NULL,
  `status` tinyint(4) DEFAULT NULL,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `staffable_type` varchar(255) DEFAULT NULL,
  `staffable_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_role_id_foreign` (`role_id`),
  KEY `staff_staffable_type_staffable_id_index` (`staffable_type`,`staffable_id`),
  KEY `staff_user_id_foreign` (`user_id`),
  CONSTRAINT `staff_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staff_clothes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_clothes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` bigint(20) unsigned DEFAULT NULL,
  `clothe_name` varchar(255) DEFAULT NULL,
  `clothing_size` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `cloth_id` bigint(20) unsigned DEFAULT NULL,
  `epp_id` bigint(20) unsigned DEFAULT NULL,
  `color_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) DEFAULT 'Pendiente',
  `condition` enum('Nuevo','En Almacén') NOT NULL DEFAULT 'Nuevo',
  PRIMARY KEY (`id`),
  KEY `staff_clothes_staff_id_foreign` (`staff_id`),
  KEY `staff_clothes_cloth_id_foreign` (`cloth_id`),
  KEY `staff_clothes_color_id_foreign` (`color_id`),
  KEY `staff_clothes_epp_id_foreign` (`epp_id`),
  CONSTRAINT `staff_clothes_cloth_id_foreign` FOREIGN KEY (`cloth_id`) REFERENCES `cloths` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_clothes_color_id_foreign` FOREIGN KEY (`color_id`) REFERENCES `colors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_clothes_epp_id_foreign` FOREIGN KEY (`epp_id`) REFERENCES `epps` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_clothes_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staff_clothes_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_clothes_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `reason` varchar(255) NOT NULL DEFAULT 'Nuevo',
  `assigned_at` date NOT NULL,
  `evidence_image` varchar(255) DEFAULT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_clothes_histories_staff_id_foreign` (`staff_id`),
  KEY `staff_clothes_histories_user_id_foreign` (`user_id`),
  CONSTRAINT `staff_clothes_histories_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_clothes_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staff_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` bigint(20) unsigned DEFAULT NULL,
  `file_type` varchar(255) DEFAULT NULL,
  `file_path` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `status` varchar(255) DEFAULT 'Realizado',
  PRIMARY KEY (`id`),
  KEY `staff_files_staff_id_foreign` (`staff_id`),
  CONSTRAINT `staff_files_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staff_financials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_financials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` bigint(20) unsigned DEFAULT NULL,
  `district` varchar(255) DEFAULT NULL,
  `province` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `children` tinyint(4) DEFAULT NULL,
  `afp` varchar(255) DEFAULT NULL,
  `onp` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `account_number` varchar(255) DEFAULT NULL,
  `system_work` varchar(255) DEFAULT NULL,
  `replacement` varchar(255) DEFAULT NULL,
  `unit_id` bigint(20) unsigned DEFAULT NULL,
  `salary` double DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bank_entity` tinyint(4) DEFAULT NULL,
  `pensioncontribution` tinyint(4) DEFAULT NULL,
  `cci` varchar(255) DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_financials_staff_id_foreign` (`staff_id`),
  KEY `staff_financials_unit_id_foreign` (`unit_id`),
  CONSTRAINT `staff_financials_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_financials_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staff_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_photos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` bigint(20) unsigned NOT NULL,
  `url` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_photos_staff_id_foreign` (`staff_id`),
  CONSTRAINT `staff_photos_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subdealership_unit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subdealership_unit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint(20) unsigned DEFAULT NULL,
  `subdealership_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subdealership_unit_unit_id_foreign` (`unit_id`),
  KEY `subdealership_unit_subdealership_id_foreign` (`subdealership_id`),
  CONSTRAINT `subdealership_unit_subdealership_id_foreign` FOREIGN KEY (`subdealership_id`) REFERENCES `subdealerships` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subdealership_unit_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subdealerships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subdealerships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `ruc` varchar(255) DEFAULT NULL,
  `fiscal_address` varchar(255) DEFAULT NULL,
  `legal_address` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `dealership_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subdealerships_dealership_id_foreign` (`dealership_id`),
  CONSTRAINT `subdealerships_dealership_id_foreign` FOREIGN KEY (`dealership_id`) REFERENCES `dealerships` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned DEFAULT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `service_name` varchar(255) DEFAULT NULL,
  `amount` int(11) DEFAULT NULL,
  `um` varchar(255) DEFAULT NULL,
  `service_type` tinyint(4) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit_value` decimal(12,4) DEFAULT NULL,
  `unit_price` decimal(12,4) DEFAULT NULL,
  `sale_value` decimal(12,4) DEFAULT NULL,
  `igv` decimal(12,4) DEFAULT NULL,
  `total` decimal(12,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_details_ticket_id_foreign` (`ticket_id`),
  KEY `ticket_details_service_id_foreign` (`service_id`),
  CONSTRAINT `ticket_details_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ticket_details_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) unsigned DEFAULT NULL,
  `dinner_id` bigint(20) unsigned DEFAULT NULL,
  `dinner_name` varchar(255) DEFAULT NULL,
  `dni` varchar(255) DEFAULT NULL,
  `subdealership_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `serial_number` varchar(255) NOT NULL,
  `subdealership_ruc` varchar(255) DEFAULT NULL,
  `price_value` decimal(8,2) DEFAULT NULL,
  `igv` decimal(8,2) DEFAULT NULL,
  `status` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tickets_sale_id_foreign` (`sale_id`),
  KEY `tickets_dinner_id_foreign` (`dinner_id`),
  CONSTRAINT `tickets_dinner_id_foreign` FOREIGN KEY (`dinner_id`) REFERENCES `dinners` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `units` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `mine_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `units_mine_id_foreign` (`mine_id`),
  CONSTRAINT `units_mine_id_foreign` FOREIGN KEY (`mine_id`) REFERENCES `mines` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_role_area`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_role_area` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `area_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_role_area_user_id_foreign` (`user_id`),
  KEY `user_role_area_role_id_foreign` (`role_id`),
  KEY `user_role_area_area_id_foreign` (`area_id`),
  CONSTRAINT `user_role_area_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_role_area_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_role_area_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_units` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `unit_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_units_user_id_foreign` (`user_id`),
  KEY `user_units_unit_id_foreign` (`unit_id`),
  CONSTRAINT `user_units_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_units_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `weekly_program_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `weekly_program_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `weekly_program_id` bigint(20) unsigned NOT NULL,
  `date` date NOT NULL,
  `meal_type` varchar(255) NOT NULL,
  `dish_category_id` bigint(20) unsigned NOT NULL,
  `dish_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `weekly_program_items_weekly_program_id_foreign` (`weekly_program_id`),
  KEY `weekly_program_items_dish_category_id_foreign` (`dish_category_id`),
  KEY `weekly_program_items_dish_id_foreign` (`dish_id`),
  CONSTRAINT `weekly_program_items_dish_category_id_foreign` FOREIGN KEY (`dish_category_id`) REFERENCES `dish_categories` (`id`),
  CONSTRAINT `weekly_program_items_dish_id_foreign` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`),
  CONSTRAINT `weekly_program_items_weekly_program_id_foreign` FOREIGN KEY (`weekly_program_id`) REFERENCES `weekly_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `weekly_programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `weekly_programs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cafe_id` bigint(20) unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'borrador',
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `weekly_programs_cafe_id_foreign` (`cafe_id`),
  KEY `weekly_programs_user_id_foreign` (`user_id`),
  CONSTRAINT `weekly_programs_cafe_id_foreign` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `weekly_programs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2025_05_05_195456_create_permission_tables',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2025_05_07_211433_add_fields_to_permissions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2025_05_05_214306_create_areas_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2025_05_08_195332_create_mines_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2025_05_08_200034_create_units_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2025_05_08_200111_create_cafes_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2025_05_12_160249_create_ingredient_categories_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2025_05_12_160859_create_dish_categories_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2025_05_12_162151_create_dishes_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2025_05_12_162203_create_ingredients_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2025_05_12_215634_create_dish_ingredient_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2025_05_13_161247_create_levels_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2025_05_13_165359_create_dish_level_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2025_05_13_165801_create_dosifications_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2025_05_16_135520_create_businesses_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2025_05_20_144212_create_headquarters_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2025_05_20_150220_add_fields_to_areas',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2025_05_20_160828_create_services_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2025_05_20_161152_create_business_service_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2025_05_20_161243_create_cafe_service_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2025_05_22_151257_add_fields_to_businesses',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2025_05_27_205444_create_dealerships_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2025_05_27_221517_create_contracts_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2025_05_27_222205_create_addendums_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2025_06_02_195023_create_subdealerships_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2025_06_02_200025_create_dinners_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2025_06_02_231536_create_serviceables_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2025_06_03_151208_create_subdealership_unit_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2025_06_04_172830_create_payment_methods_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2025_06_04_173647_create_receipt_types_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2025_06_04_173916_create_sale_types_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2025_06_09_165647_create_cafe_user_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2025_06_09_171127_add_fields_to_roles',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2025_06_10_161416_create_sales_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2025_06_10_161452_create_sale_details_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2025_06_10_161600_create_tickets_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2025_06_11_145949_add_fields_to_areas',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2025_06_11_173530_remove_fields_from_sales',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2025_06_12_153322_create_area_role_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2025_06_12_155414_add_fields_to_tickets',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2025_06_13_154833_create_user_role_area_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2025_06_13_164158_create_ticket_details_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2025_06_19_174303_remove_fields_from_sales',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2025_06_19_202722_create_businessables_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2025_06_25_164323_add_fields_to_sales',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2025_06_25_165424_add_fields_to_services',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2025_06_27_113821_create_dish_category_serviceable_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2025_06_30_113855_create_dish_ingredient_level_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2025_06_30_122124_drop_dish_level_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2025_06_30_144441_create_dish_category_dish_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2025_06_30_154949_remove_fields_from_dishes',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2025_07_07_181154_create_cities_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2025_07_07_181225_create_providers_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2025_07_08_103842_create_measurement_units_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2025_07_08_103930_create_city_provider_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2025_07_08_104003_create_ingredient_city_provider_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2025_07_08_104204_add_fields_to_ingredients',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2025_07_11_102638_add_fields_to_users',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2025_07_11_113749_drop_dish_ingredient_level_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2025_07_11_113940_create_dish_ingredient_levels_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2025_07_11_114251_create_gross_weights_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2025_07_11_114747_create_ingredient_costs_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2025_07_11_115541_create_net_weights_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2025_07_11_115552_create_solid_wastes_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2025_07_11_115607_create_calories_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2025_07_14_104116_create_liquid_wastes_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2025_07_18_123544_add_fields_to_dishes',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2025_11_20_111108_create_cafe_roles_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2025_11_20_112654_create_guards_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2025_11_20_113109_create_guard_roles_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2025_11_28_100748_create_periods_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2025_11_28_100933_create_period_users_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2025_12_03_105415_create_staff_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2025_12_04_083202_create_staff_files_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2025_12_05_103314_create_staff_financials_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2025_12_10_095221_create_staff_clothes_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2025_12_15_112125_create_observations_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2025_12_16_100644_add_expiration_date_to_staff_files_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2025_08_08_104927_drop_dish_category_serviceable_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2025_08_08_105455_create_dish_category_serviceables_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2026_01_06_101843_create_area_headquarter_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2026_01_06_102545_drop_fields_to_areas',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2026_01_06_114156_drop_fields_to_staff',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2026_01_06_114519_add_fields_to_staff',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2026_01_07_090914_add_fields_to_staff',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2026_01_07_113724_add_fields_to_staff_financials',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2026_01_09_113610_drop_fields_to_guard_roles',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2026_01_09_113658_add_fields_to_guard_roles',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2026_01_12_122618_create_period_staffs_table',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2026_01_12_124409_drop_period_users_table',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2026_01_13_115755_create_staff_photos_table',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2026_01_15_092604_create_user_units_table',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2026_01_20_090758_add_fields_to_mines',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2026_01_20_091857_create_mine_subdealerships_table',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2026_01_23_110740_create_cloths_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2026_01_23_113210_create_cloth_role_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2026_01_23_115554_add_cloth_id_to_staff_clothes_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2026_01_27_104023_add_status_to_staff_clothes_table',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2026_01_28_093600_add_cafe_id_to_cloth_role_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2026_01_28_095912_create_colors_table',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2026_01_28_095924_create_cloth_inventories_table',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2026_01_28_095937_add_color_id_to_staff_clothes_table',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2026_02_02_090934_create_computer_equipments_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2026_02_02_090941_create_kitchen_equipments_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2026_02_02_090948_create_inventory_stocks_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2026_02_06_084111_create_menu_management_tables',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2026_02_09_122418_add_extra_fields_to_kitchen_equipments_table',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2026_02_09_124813_create_recipes_table',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2026_02_09_124823_create_menu_cycles_table',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2026_02_09_124854_create_daily_menus_table',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2026_02_09_124902_create_service_types_table',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2026_02_12_085838_add_ingredients_permission',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2026_02_12_165801_create_dosifications_table',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2026_02_13_085904_add_extra_nutrients_to_dosifications_table',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2026_02_13_093609_update_precision_in_dosifications_table',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2026_02_17_121555_create_dish_recipes_and_compositions_tables',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2026_02_18_092158_modify_ingredients_table_remove_fields_add_waste',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2026_02_18_100657_increase_amount_column_in_ingredients_table',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2026_02_18_125953_add_energy_to_ingredients_table',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (177,'2026_02_19_091638_add_totals_to_dish_recipes_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (178,'2026_02_19_094544_add_unit_price_to_dish_recipe_ingredients_table',60);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (179,'2026_03_05_095138_create_cloth_invoices_table',61);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2026_03_05_095142_create_cloth_invoice_items_table',61);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2026_03_05_100901_add_headquarter_id_to_cloth_invoices_table',62);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2026_03_05_101853_add_type_to_providers_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2026_03_05_103406_create_cloth_providers_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2026_03_05_103429_change_provider_id_to_cloth_provider_id_in_cloth_invoices_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (186,'2026_03_05_110001_create_epps_table',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2026_03_05_110012_create_cloth_provider_epp_table',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (188,'2026_03_05_123155_create_epp_sizes_table',65);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (189,'2026_03_06_084458_create_cloth_cloth_provider_table',66);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (190,'2026_03_06_093946_add_cost_price_to_epps_table',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (191,'2026_03_06_100651_add_size_to_cloth_invoice_items_table',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (192,'2026_03_06_100730_modify_cloth_invoice_items_for_epps',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (193,'2026_03_06_105508_add_quantity_to_cloths_table',70);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2026_03_10_085845_add_invoice_image_to_cloth_invoices_table',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2026_03_11_094711_create_epp_city_providers_table',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2026_03_11_094830_remove_cost_price_from_epps_table',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2026_03_12_091131_add_user_id_to_cloth_invoices_table',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (198,'2026_03_13_112721_add_unit_id_to_inventory_stocks_and_create_transfers_tables',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (199,'2026_03_13_120142_add_epp_id_to_staff_clothes_table',75);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (200,'2026_03_13_124546_create_sizes_and_epp_size_tables',76);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (201,'2026_03_16_085355_remove_city_id_from_epp_sizes_table',77);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (202,'2026_03_16_100733_create_category_epps_table',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (203,'2026_03_16_100831_add_category_epp_id_to_epps_table',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (204,'2026_03_16_103559_create_epp_role_table',79);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (205,'2026_03_16_114154_add_size_to_inventory_stocks_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (206,'2026_03_16_123231_add_color_id_to_inventory_stocks_table',81);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (207,'2026_03_17_121310_add_quantity_and_color_to_epp_role_table',82);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (208,'2026_03_17_121523_add_quantity_to_staff_clothes_table',83);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (209,'2026_03_19_095821_create_staff_clothes_histories_table',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (210,'2026_03_25_110624_add_color_id_to_inventory_transfer_items_table',85);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (211,'2026_03_26_082548_add_evidence_image_to_staff_clothes_histories_table',86);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (212,'2026_03_26_094230_add_condition_to_inventory_stocks_table',87);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (213,'2026_03_26_094248_add_condition_to_staff_clothes_table',87);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (214,'2026_04_11_093025_add_observation_to_guard_roles_table',88);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (215,'2026_04_23_105710_add_status_to_staff_files_table',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (217,'2026_04_28_093534_create_nutritional_factors_table',90);
