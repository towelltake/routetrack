/*
Customer visit CFT detail -- MySQL 8.0+ (CTEs required).
Install in the intended SFA database. Replaces only sp_cft_report; business
tables and sp_daily_summary are untouched. Calls only SELECT data.

Parameters and date scope match sp_daily_summary:
  CALL sp_cft_report('ALL', '2026-11-01', '2026-11-16');
  CALL sp_cft_report('DIVISION_CODE', '2026-11-01', '2026-11-16');
Division matches routemaster.divisioncode (not cmpycode). ALL is case-insensitive
and ignores surrounding spaces. Dates filter journey START date inclusively.
Only active routes and active customers are included. Overnight visits retain
their actual dates and belong to their recorded routekey.

One row per on-premises visit (onpremisevisit = 1), including repeat, incomplete
and unplanned visits. Off-premises, NULL and other premises flags are excluded.
Unvisited customers and routes without visits have no detail rows.
START/END DATE/TIME are customer check-in/out, not route start/end.
Achieved CFT per visit is raw valid elapsed H:MM:SS (not limited to 24 hours).
Missing timestamps or incomplete/reversed durations display 0.
SALESMANCODE uses the journey salesman alternate code, falling back to the
journey's internal salesmancode. CUST CODE uses customer alternatecode, falling
back to internal customercode; CUSTOMER NAME uses customermaster.customername.

Planned CFT follows the daily-summary JP definition:
* Planned CFT is the scheduled journey/customer plan target in minutes,
  MAX(nonnegative cft) across duplicate plan rows; zero for unplanned customers.
  It repeats on revisits for reference: do not sum this column as a plan total.
Planned CFT uses DECIMAL(18,2) with bounded linked-server metadata.
This standalone procedure does not enforce application session authorization;
any application integration must apply the permitted route/company scope.
SQL SECURITY INVOKER retains the database caller's underlying privileges.
*/

/*!50003 DROP PROCEDURE IF EXISTS `sp_cft_report` */;

DELIMITER $$

/*!50003 CREATE DEFINER=CURRENT_USER PROCEDURE `sp_cft_report`(
    IN in_divisioncode VARCHAR(50),
    IN in_startdate DATE,
    IN in_enddate DATE
)
SQL SECURITY INVOKER
READS SQL DATA
BEGIN
    IF in_startdate IS NULL OR in_enddate IS NULL OR in_enddate < in_startdate THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Supply a valid inclusive start-date range';
    END IF;
    IF in_divisioncode IS NULL OR TRIM(in_divisioncode) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Division code is required';
    END IF;

    WITH
    journeys AS (
        SELECT s.routekey, s.routecode, s.salesmancode,
            CASE WHEN s.routestartdate IS NOT NULL
                      AND CAST(s.routestartdate AS CHAR) NOT LIKE '0000-%'
                 THEN TIMESTAMP(DATE(s.routestartdate), COALESCE(NULLIF(s.routestarttime, ''), '00:00:00')) END AS start_at
        FROM startendday s
        JOIN routemaster rm ON rm.routecode = s.routecode
        WHERE (UPPER(TRIM(in_divisioncode)) = 'ALL' OR rm.divisioncode = TRIM(in_divisioncode))
          AND rm.activestatus = 1
          AND s.routestartdate >= in_startdate
          AND s.routestartdate < DATE_ADD(in_enddate, INTERVAL 1 DAY)
    ),
    plans AS (
        SELECT p.routekey, p.customercode, MAX(GREATEST(COALESCE(p.cft, 0), 0)) AS cft
        FROM routesequencecustomerstatus p
        JOIN journeys j ON j.routekey = p.routekey
        JOIN customermaster c ON c.customercode = p.customercode AND c.activecustomer = 1
        WHERE p.schelduledflag = 1
        GROUP BY p.routekey, p.customercode
    ),
    visits AS (
        SELECT v.routekey, v.logkey, v.customercode, c.alternatecode, c.customername,
            v.onpremisevisit,
            CASE WHEN v.logstartdate IS NOT NULL AND v.logstarttime IS NOT NULL
                      AND CAST(v.logstartdate AS CHAR) NOT LIKE '0000-%'
                 THEN TIMESTAMP(v.logstartdate, v.logstarttime) END AS check_in,
            CASE WHEN v.logenddate IS NOT NULL AND v.logendtime IS NOT NULL
                      AND CAST(v.logenddate AS CHAR) NOT LIKE '0000-%'
                 THEN TIMESTAMP(v.logenddate, v.logendtime) END AS check_out
        FROM customervisitlog v
        JOIN journeys j ON j.routekey = v.routekey
        JOIN customermaster c ON c.customercode = v.customercode AND c.activecustomer = 1
    ),
    visit_facts AS (
        SELECT v.*, COALESCE(p.cft, 0) AS planned_cft,
            CASE WHEN v.check_out >= v.check_in
                 THEN TIMESTAMPDIFF(SECOND, v.check_in, v.check_out) END AS duration_seconds
        FROM visits v
        LEFT JOIN plans p ON p.routekey = v.routekey AND p.customercode = v.customercode
    )
    SELECT j.routecode AS `ROUTECODE`,
        COALESCE(NULLIF(s.alternatesalesmancode, ''), CAST(j.salesmancode AS CHAR), '0') AS `SALESMANCODE`,
        IFNULL(s.salesmanname1, '0') AS `SALESMAN`,
        COALESCE(NULLIF(v.alternatecode, ''), CAST(v.customercode AS CHAR)) AS `CUST CODE`,
        IFNULL(v.customername, '0') AS `CUSTOMER NAME`,
        IFNULL(DATE_FORMAT(v.check_in, '%d/%m/%Y'), '0') AS `START DATE`,
        IFNULL(DATE_FORMAT(v.check_in, '%H:%i:%s'), '0') AS `START TIME`,
        IFNULL(DATE_FORMAT(v.check_out, '%d/%m/%Y'), '0') AS `END DATE`,
        IFNULL(DATE_FORMAT(v.check_out, '%H:%i:%s'), '0') AS `END TIME`,
        IFNULL(CONCAT(FLOOR(v.duration_seconds / 3600), ':',
            LPAD(FLOOR(MOD(v.duration_seconds, 3600) / 60), 2, '0'), ':',
            LPAD(MOD(v.duration_seconds, 60), 2, '0')), '0') AS `Achieved CFT per visit`,
        CAST(v.planned_cft AS DECIMAL(18,2)) AS `Planned CFT`
    FROM visit_facts v
    JOIN journeys j ON j.routekey = v.routekey
    LEFT JOIN salesman s ON s.salesmancode = j.salesmancode
    WHERE v.onpremisevisit = 1
    ORDER BY j.start_at, j.routecode, j.routekey, v.check_in, v.logkey;
END */$$

DELIMITER ;
