/*
Customer visit qualification report -- MySQL 8.0+.
One row per individual visit, including repeats, incomplete and unplanned visits.
Unvisited planned customers and routes without visits have no rows.
Planned Call = scheduled journey/customer plan exists (schelduledflag = 1).
Actual Visit = Yes for each recorded visit, including incomplete/off-premises visits.
CFT Achieved = this individual visit qualifies, whether planned or unplanned.
Non-Premise Vist (OTP) = onpremisevisit exactly 0, per user confirmation.
NULL/other flag values return No. This indicator is independent of matched OTP
requests used for CFT exclusion. All four indicators return Yes/No.
Division displays routemaster.divisioncode, as in the daily summary.
Example: CALL sp_customer_visit_report('ALL', '2026-11-01', '2026-11-16');
Parameters match sp_daily_summary: division code, inclusive journey-start dates.
Trimmed case-insensitive ALL includes all divisions; active routes/customers only.
CFT qualification uses each recorded visit's positive cft target, completed valid
on-premises timing, and no matched OTP. Separate short revisits are never summed.
Historical journey salesman is used. Customer alternate code falls back to the
internal code; customer name comes from customermaster.customername.
Standalone SQL SECURITY INVOKER report; application integration must enforce
session route/company/geographic scope. No business data writes. Installation
replaces only sp_customer_visit_report; other procedures are untouched.
*/

/*!50003 DROP PROCEDURE IF EXISTS `sp_customer_visit_report` */;

DELIMITER $$

/*!50003 CREATE DEFINER=CURRENT_USER PROCEDURE `sp_customer_visit_report`(
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
        SELECT s.routekey, s.routecode, s.salesmancode, rm.divisioncode,
            CASE WHEN s.routestartdate IS NOT NULL
                      AND CAST(s.routestartdate AS CHAR) NOT LIKE '0000-%'
                 THEN TIMESTAMP(DATE(s.routestartdate), COALESCE(NULLIF(s.routestarttime, ''), '00:00:00')) END AS start_at,
            CASE WHEN s.routeclosed = 1 AND s.routeenddate IS NOT NULL
                      AND CAST(s.routeenddate AS CHAR) NOT LIKE '0000-%'
                      AND NULLIF(s.routeendtime, '') IS NOT NULL
                 THEN TIMESTAMP(DATE(s.routeenddate), s.routeendtime) END AS close_at
        FROM startendday s
        JOIN routemaster rm ON rm.routecode = s.routecode
        WHERE (UPPER(TRIM(in_divisioncode)) = 'ALL' OR rm.divisioncode = TRIM(in_divisioncode))
          AND rm.activestatus = 1
          AND s.routestartdate >= in_startdate
          AND s.routestartdate < DATE_ADD(in_enddate, INTERVAL 1 DAY)
    ),
    journey_windows AS (
        SELECT j.*,
            (SELECT MIN(TIMESTAMP(DATE(n.routestartdate), COALESCE(NULLIF(n.routestarttime, ''), '00:00:00')))
             FROM startendday n
             WHERE n.routecode = j.routecode
               AND TIMESTAMP(DATE(n.routestartdate), COALESCE(NULLIF(n.routestarttime, ''), '00:00:00')) > j.start_at
            ) AS next_start_at
        FROM journeys j
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
            v.onpremisevisit, GREATEST(COALESCE(v.cft, 0), 0) AS visit_cft,
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
    otp_candidates AS (
        SELECT o.otplogid, v.routekey, v.logkey,
            ROW_NUMBER() OVER (PARTITION BY o.otplogid ORDER BY j.start_at, j.routekey,
                ABS(TIMESTAMPDIFF(SECOND, v.check_in, TIMESTAMP(o.otpdate, o.otptime))),
                v.check_in, v.logkey) AS candidate_rank
        FROM otplogdetail o
        JOIN journey_windows j ON j.routecode = o.routecode
            AND TIMESTAMP(o.otpdate, o.otptime) >= j.start_at
            AND (j.close_at IS NULL OR TIMESTAMP(o.otpdate, o.otptime) <= j.close_at)
            AND (j.next_start_at IS NULL OR TIMESTAMP(o.otpdate, o.otptime) < j.next_start_at)
        JOIN visits v ON v.routekey = j.routekey AND v.customercode = o.customercode
            AND v.check_in IS NOT NULL
    ),
    otp_visits AS (
        SELECT DISTINCT routekey, logkey FROM otp_candidates WHERE candidate_rank = 1
    ),
    visit_facts AS (
        SELECT v.*,
            CASE WHEN o.logkey IS NOT NULL THEN 1 ELSE 0 END AS otp,
            CASE WHEN o.logkey IS NULL AND v.onpremisevisit = 1 AND v.visit_cft > 0
                      AND v.check_out >= v.check_in
                      AND TIMESTAMPDIFF(SECOND, v.check_in, v.check_out) >= v.visit_cft * 60
                 THEN 1 ELSE 0 END AS qualified
        FROM visits v
        LEFT JOIN otp_visits o ON o.routekey = v.routekey AND o.logkey = v.logkey
    )
    SELECT IFNULL(j.divisioncode, '0') AS `Division`,
        j.routecode AS `Route Code`,
        IFNULL(s.salesmanname1, '0') AS `Salesman Name`,
        COALESCE(NULLIF(c.alternatecode, ''), CAST(c.customercode AS CHAR)) AS `Cust. Code`,
        IFNULL(c.customername, '0') AS `Cust. Name`,
        CASE WHEN p.customercode IS NOT NULL THEN 'Yes' ELSE 'No' END AS `Planned Call`,
        'Yes' AS `Actual Visit`,
        CASE WHEN v.qualified = 1 THEN 'Yes' ELSE 'No' END AS `CFT Achieved`,
        CASE WHEN v.onpremisevisit = 0 THEN 'Yes' ELSE 'No' END AS `Non-Premise Vist (OTP)`
    FROM visit_facts v
    JOIN journeys j ON j.routekey = v.routekey
    JOIN customermaster c ON c.customercode = v.customercode AND c.activecustomer = 1
    LEFT JOIN salesman s ON s.salesmancode = j.salesmancode
    LEFT JOIN plans p ON p.routekey = v.routekey AND p.customercode = v.customercode
    ORDER BY j.start_at, j.routecode, j.routekey, v.check_in, v.logkey;
END */$$

DELIMITER ;
