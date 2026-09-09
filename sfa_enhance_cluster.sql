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

/*Table structure for table `clustermaster` */

DROP TABLE IF EXISTS `clustermaster`;

CREATE TABLE `clustermaster` (
  `clustercode` bigint NOT NULL DEFAULT '0',
  `clustername` varchar(30) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `arbclustername` varchar(30) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `created` char(20) DEFAULT NULL,
  `cdat` datetime DEFAULT NULL,
  `modified` char(20) DEFAULT NULL,
  `mdat` datetime DEFAULT NULL,
  `activestatus` int DEFAULT '1',
  PRIMARY KEY (`clustercode`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*Data for the table `clustermaster` */

insert  into `clustermaster`(`clustercode`,`clustername`,`arbclustername`,`created`,`cdat`,`modified`,`mdat`,`activestatus`) values 
(1,'ENHANCE CLUSTER','ENHANCE CLUSTER',NULL,NULL,NULL,NULL,1);

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
