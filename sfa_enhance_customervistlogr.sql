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

/*Table structure for table `customervisitlog` */

DROP TABLE IF EXISTS `customervisitlog`;

CREATE TABLE `customervisitlog` (
  `routekey` int NOT NULL,
  `logkey` int NOT NULL,
  `routecode` int DEFAULT NULL,
  `salesmancode` int DEFAULT NULL,
  `customercode` int NOT NULL,
  `logstartdate` date NOT NULL,
  `logstarttime` time NOT NULL,
  `logenddate` date NOT NULL,
  `logendtime` time NOT NULL,
  `cft` int DEFAULT '0',
  `mdate` datetime DEFAULT NULL,
  PRIMARY KEY (`routekey`,`logkey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
