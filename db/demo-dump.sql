-- MySQL dump 10.13  Distrib 8.0.40, for macos12.7 (arm64)
--
-- Host: localhost    Database: kamc_lounge
-- ------------------------------------------------------
-- Server version	8.0.40

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `amenities`
--

DROP TABLE IF EXISTS `amenities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amenities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inspection_relevant` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_amenities_sort` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `amenities`
--

LOCK TABLES `amenities` WRITE;
/*!40000 ALTER TABLE `amenities` DISABLE KEYS */;
INSERT INTO `amenities` VALUES (1,10,'Location / Übersicht','Das Obergeschoss der Lounge oben: Terrasse mit Rheinblick plus Innenbereich, rund 24 Sitzplätze.','/assets/img/lounge/hero-rheinauhafen-800.webp','Rücksicht auf Nachbarschaft — keine Musik, keine Lautsprecher.',0,1,'2026-07-01 12:09:31','2026-07-01 13:35:25'),(2,20,'Kühlschrank','Kühlschrank zur freien Nutzung während deiner Buchung.','/assets/img/lounge/couch-02-800.webp','Nach dem Termin bitte komplett leeren — der Inhalt wird nicht gestellt.',1,1,'2026-07-01 12:09:31','2026-07-01 13:35:25'),(3,30,'Sitzgruppe','Gemütliche Lounge-Sitzgruppe im Innenbereich.','/assets/img/lounge/couch-01-800.webp','Möbel nach dem Termin an den ursprünglichen Platz zurückstellen.',1,1,'2026-07-01 12:09:31','2026-07-01 13:34:57'),(4,40,'Esstisch mit Stühlen','Großer Esstisch mit Stühlen für gemeinsame Runden.','/assets/img/lounge/esstisch-800.webp','Sauber hinterlassen, Stühle zurückstellen.',1,1,'2026-07-01 12:09:31','2026-07-01 13:35:25'),(5,50,'Deck Chairs','Liegestühle für die Terrasse.','/assets/img/lounge/aussicht-treppe-800.webp','Bei Wind sichern; nach Nutzung zusammenklappen und verstauen.',0,1,'2026-07-01 12:09:31','2026-07-01 13:34:57'),(6,60,'Gasgrill „Burnhard\"','Vom Verein gestellter Gasgrill inkl. Haupt- und Ersatz-Gasflasche.','/assets/img/lounge/grill-800.webp','Grill nach Nutzung reinigen, auf Brandflecken prüfen, Terrasse kehren. Gas zudrehen.',1,1,'2026-07-01 12:09:31','2026-07-01 13:34:57');
/*!40000 ALTER TABLE `amenities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blackouts`
--

DROP TABLE IF EXISTS `blackouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blackouts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `blackout_date` date NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blackout_date` (`blackout_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blackouts`
--

LOCK TABLES `blackouts` WRITE;
/*!40000 ALTER TABLE `blackouts` DISABLE KEYS */;
INSERT INTO `blackouts` VALUES (1,'2026-08-12','Sommerfest','2026-07-01 11:57:14');
/*!40000 ALTER TABLE `blackouts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint unsigned NOT NULL,
  `booking_date` date NOT NULL,
  `slot` enum('tag','abend') COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_utc` datetime NOT NULL,
  `end_utc` datetime NOT NULL,
  `status` enum('pending','confirmed','rejected','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `party_size` smallint unsigned NOT NULL,
  `purpose` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hausordnung_accepted_at` datetime NOT NULL,
  `hausordnung_version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `decided_by` bigint unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decision_note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inspection_result` enum('passed','rework') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inspected_by` bigint unsigned DEFAULT NULL,
  `inspected_at` datetime DEFAULT NULL,
  `inspection_notes` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `case_status` enum('none','rework','disputed','escalated','resolved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `rework_due` date DEFAULT NULL,
  `resolution` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolved_by` bigint unsigned DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_slot` (`booking_date`,`slot`,`status`),
  KEY `idx_expiry` (`status`,`created_at`),
  KEY `idx_member` (`member_id`,`booking_date`),
  KEY `idx_abnahme` (`status`,`end_utc`,`inspection_result`),
  KEY `fk_bookings_decider` (`decided_by`),
  KEY `fk_bookings_inspector` (`inspected_by`),
  KEY `idx_case` (`case_status`),
  KEY `fk_bookings_resolver` (`resolved_by`),
  CONSTRAINT `fk_bookings_decider` FOREIGN KEY (`decided_by`) REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_inspector` FOREIGN KEY (`inspected_by`) REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_resolver` FOREIGN KEY (`resolved_by`) REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_booking_time` CHECK ((`end_utc` > `start_utc`)),
  CONSTRAINT `chk_party_size` CHECK ((`party_size` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (1,1,'2026-08-10','abend','2026-08-10 16:00:00','2026-08-11 00:00:00','confirmed',10,NULL,'2026-07-01 09:57:14','2026-1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-01 11:57:14','2026-07-01 11:57:14','none',NULL,NULL,NULL,NULL),(3,4,'2026-09-20','tag','2026-09-20 06:00:00','2026-09-20 16:00:00','confirmed',12,'Crew-Grillen','2026-07-01 10:17:15','2026-1',3,'2026-07-01 10:17:16',NULL,NULL,NULL,NULL,NULL,'2026-07-01 10:17:15','2026-07-01 10:17:16','none',NULL,NULL,NULL,NULL),(4,2,'2026-10-03','abend','2026-10-03 16:00:00','2026-10-04 00:00:00','confirmed',14,'Herbst-Törn Crew','2026-07-01 10:23:40','2026-1',3,'2026-07-01 12:13:29',NULL,NULL,NULL,NULL,NULL,'2026-07-01 10:23:40','2026-07-01 12:13:29','none',NULL,NULL,NULL,NULL),(5,4,'2026-09-27','tag','2026-09-27 06:00:00','2026-09-27 16:00:00','pending',9,'Airbnb-Test','2026-07-01 10:44:49','2026-1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-01 10:44:49','2026-07-01 10:44:49','none',NULL,NULL,NULL,NULL),(6,2,'2026-07-03','abend','2026-07-03 16:00:00','2026-07-04 00:00:00','pending',6,NULL,'2026-07-01 12:10:14','2026-1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-01 12:10:14','2026-07-01 12:10:14','none',NULL,NULL,NULL,NULL),(7,3,'2026-07-04','abend','2026-07-04 16:00:00','2026-07-05 00:00:00','pending',9,'wow','2026-07-01 12:36:48','2026-1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-01 12:36:48','2026-07-01 12:36:48','none',NULL,NULL,NULL,NULL),(8,4,'2026-06-20','tag','2026-06-20 06:00:00','2026-06-20 16:00:00','confirmed',8,NULL,'2026-07-01 12:47:27','2026-1',3,'2026-07-01 12:47:27',NULL,'passed',3,'2026-07-01 12:47:52','Grill sauber, gekehrt, keine Brandflecken','2026-07-01 14:47:27','2026-07-01 12:47:52','none',NULL,NULL,NULL,NULL),(9,4,'2026-06-25','abend','2026-06-25 16:00:00','2026-06-26 00:00:00','confirmed',12,NULL,'2026-07-01 12:53:03','2026-1',3,'2026-07-01 12:53:03',NULL,NULL,NULL,NULL,NULL,'2026-07-01 14:53:03','2026-07-01 14:53:03','none',NULL,NULL,NULL,NULL),(10,4,'2026-06-15','tag','2026-06-15 06:00:00','2026-06-15 16:00:00','confirmed',10,NULL,'2026-07-01 12:53:03','2026-1',3,'2026-07-01 12:53:03',NULL,'passed',3,'2026-07-01 12:53:03','Brandfleck auf der Terrasse, Grillrost nicht gereinigt — Nacharbeit nötig.','2026-07-01 14:53:03','2026-07-01 13:18:52','resolved',NULL,'kulanz',2,'2026-07-01 13:18:52'),(11,4,'2026-06-10','tag','2026-06-10 06:00:00','2026-06-10 16:00:00','confirmed',8,NULL,'2026-07-01 13:19:45','2026-1',3,'2026-07-01 13:19:45',NULL,'rework',3,'2026-07-01 13:19:45','Kühlschrank nicht geleert, Boden klebrig.','2026-07-01 15:19:45','2026-07-01 15:19:45','rework','2026-07-10',NULL,NULL,NULL),(12,4,'2026-06-05','abend','2026-06-05 16:00:00','2026-06-06 00:00:00','confirmed',14,NULL,'2026-07-01 13:19:45','2026-1',3,'2026-07-01 13:19:45',NULL,'rework',3,'2026-07-01 13:19:45','Zerbrochenes Glas im Ausguss, Fliese gesprungen.','2026-07-01 15:19:45','2026-07-01 15:19:45','escalated',NULL,NULL,NULL,NULL),(13,4,'2026-09-28','tag','2026-09-28 06:00:00','2026-09-28 16:00:00','pending',7,NULL,'2026-07-01 14:14:27','2026-1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-01 14:14:27','2026-07-01 14:14:27','none',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inspection_events`
--

DROP TABLE IF EXISTS `inspection_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inspection_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `event_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ie_booking` (`booking_id`,`created_at`),
  KEY `fk_ie_actor` (`actor_id`),
  CONSTRAINT `fk_ie_actor` FOREIGN KEY (`actor_id`) REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ie_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inspection_events`
--

LOCK TABLES `inspection_events` WRITE;
/*!40000 ALTER TABLE `inspection_events` DISABLE KEYS */;
INSERT INTO `inspection_events` VALUES (1,10,4,'dispute','Der Brandfleck war schon vorher da.','2026-07-01 13:18:51'),(2,10,2,'resolve','kulanz: Kulanz, mit Mitglied besprochen','2026-07-01 13:18:52'),(3,12,3,'rework','Fliese gesprungen.','2026-07-01 15:19:45'),(4,12,3,'escalate','Sachschaden, bitte Vorstand: Kostenfrage klären.','2026-07-01 15:19:45');
/*!40000 ALTER TABLE `inspection_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inspection_photos`
--

DROP TABLE IF EXISTS `inspection_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inspection_photos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_booking` (`booking_id`),
  KEY `fk_ip_uploader` (`uploaded_by`),
  CONSTRAINT `fk_ip_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ip_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inspection_photos`
--

LOCK TABLES `inspection_photos` WRITE;
/*!40000 ALTER TABLE `inspection_photos` DISABLE KEYS */;
INSERT INTO `inspection_photos` VALUES (1,8,'/uploads/abnahmen/1e93b9a13c796668.webp',3,'2026-07-01 12:47:54');
/*!40000 ALTER TABLE `inspection_photos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('member','hafenmeister','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `status` enum('active','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `booking_blocked` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_members_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `members`
--

LOCK TABLES `members` WRITE;
/*!40000 ALTER TABLE `members` DISABLE KEYS */;
INSERT INTO `members` VALUES (1,'dev@kamc.koeln','x','Dev Mitglied','member','active','2026-07-01 11:57:14','2026-07-01 11:57:14',0),(2,'admin@kamc.dev','$2y$12$YbOnrQf7fJ4Z4Vqc4sh5h.TfonF2WnxAMh0N4cA.Bijkig8kePrxC','Vorstand Admin','admin','active','2026-07-01 12:09:32','2026-07-01 12:09:32',0),(3,'hafen@kamc.dev','$2y$12$YbOnrQf7fJ4Z4Vqc4sh5h.TfonF2WnxAMh0N4cA.Bijkig8kePrxC','Hafenmeister RSK','hafenmeister','active','2026-07-01 12:09:32','2026-07-01 12:09:32',0),(4,'member@kamc.dev','$2y$12$YbOnrQf7fJ4Z4Vqc4sh5h.TfonF2WnxAMh0N4cA.Bijkig8kePrxC','Mitglied Muster','member','active','2026-07-01 12:09:32','2026-07-01 15:19:45',0);
/*!40000 ALTER TABLE `members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opening_hours`
--

DROP TABLE IF EXISTS `opening_hours`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `opening_hours` (
  `weekday` tinyint unsigned NOT NULL,
  `open_time` time NOT NULL DEFAULT '08:00:00',
  `close_time` time NOT NULL DEFAULT '02:00:00',
  `is_closed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`weekday`),
  CONSTRAINT `chk_weekday` CHECK ((`weekday` between 1 and 7))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opening_hours`
--

LOCK TABLES `opening_hours` WRITE;
/*!40000 ALTER TABLE `opening_hours` DISABLE KEYS */;
INSERT INTO `opening_hours` VALUES (1,'08:00:00','02:00:00',0),(2,'08:00:00','02:00:00',0),(3,'08:00:00','02:00:00',0),(4,'08:00:00','02:00:00',0),(5,'08:00:00','02:00:00',0),(6,'08:00:00','02:00:00',0),(7,'08:00:00','02:00:00',0);
/*!40000 ALTER TABLE `opening_hours` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` tinyint unsigned NOT NULL DEFAULT '1',
  `evening_start_local` time NOT NULL DEFAULT '18:00:00',
  `lead_time_hours` smallint unsigned NOT NULL DEFAULT '24',
  `pending_expiry_hours` smallint unsigned NOT NULL DEFAULT '48',
  `max_party_size` smallint unsigned NOT NULL DEFAULT '16',
  `booking_window_end` date DEFAULT '2026-10-31',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_settings_singleton` CHECK ((`id` = 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'18:00:00',24,48,16,'2026-10-31','2026-07-01 09:57:14');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-01 17:16:37
