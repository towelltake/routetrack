/*
SQLyog Community v13.3.1 (64 bit)
MySQL - 8.0.21 : Database - sfa_enhance
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`sfa_enhance` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

/*Table structure for table `channelmaster` */

DROP TABLE IF EXISTS `channelmaster`;

CREATE TABLE `channelmaster` (
  `channelcode` bigint NOT NULL AUTO_INCREMENT,
  `alternatecode` varchar(50) DEFAULT NULL,
  `channelname` varchar(50) DEFAULT NULL,
  `arbchannelname` varchar(50) DEFAULT NULL,
  `created` char(20) DEFAULT NULL,
  `customercft` int DEFAULT NULL,
  `cdat` datetime DEFAULT NULL,
  `modified` char(20) DEFAULT NULL,
  `mdat` datetime DEFAULT NULL,
  `activestatus` int DEFAULT '1',
  PRIMARY KEY (`channelcode`),
  UNIQUE KEY `u_alternatecode` (`alternatecode`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=latin1;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
