-- MySQL dump 10.13  Distrib 5.5.28, for linux2.6 (x86_64)
--
-- Host: 127.0.0.1    Database: foxtrap
-- ------------------------------------------------------
-- Server version	5.5.28

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `marks`
--

DROP TABLE IF EXISTS `marks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `marks` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(512) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `uri` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `hash` char(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT 'title + uri + tags + added',
  `tags` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT 'comma separated',
  `body` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `body_clean` mediumtext COLLATE utf8_unicode_ci NOT NULL COMMENT 'markup-free for sphinx indexing',
  `modified` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'time of last mod in browser',
  `downloaded` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'time of last completed',
  `added` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'time of add to browser',
  `last_err` varchar(1024) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `deleted` tinyint(1) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=COMPRESSED;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `searches`
--

DROP TABLE IF EXISTS `searches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `searches` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `query` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `query_hash` char(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `uses` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `query_hash` (`query_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tags` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` char(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `uses` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
