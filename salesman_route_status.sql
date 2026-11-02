/*
Salesman Route Status -- MySQL 8.0+ (CTEs and window functions required).
Schema reference: sfa_ENHANCE_LATEST_20261102.sql.
Select the intended SFA database before installing this file. Installation creates
one procedure; calling it only SELECTs data. No business tables are modified.
The installation script replaces only sp_salesman_route_status if it exists.
DEFINER=CURRENT_USER uses the installing account; SQL SECURITY INVOKER is retained.

Example (replace DIVISION_CODE with a routemaster.divisioncode value):
  CALL sp_salesman_route_status('DIVISION_CODE', '2026-10-26', '2026-10-26');

Division is matched to routemaster.divisioncode, linked by startendday.routecode.
Both dates apply to route START date, inclusive; route end date is not filtered.
Overnight visits remain attached to their journey. One result row per routekey,
including open/zero-call journeys. NULL/blank division and invalid dates are rejected.
This standalone report is not an application authorization endpoint. Any future
application integration must preserve session route/company/geographic restrictions.
SQL SECURITY INVOKER retains the database caller's underlying privileges.

Report definitions / assumptions:
* Only active customers are included in plans, visits and transaction sources.
* Calls Made = visit rows (repeats count); UN-Planned Call = visit rows for
  customers absent from the scheduled plan. Actual JP = unique scheduled customers.
* OTP: all types; match route/customer and journey window, then nearest visit
  check-in, tie-breaking by check-in/logkey, as in DashboardMetrics::otp.
  Window ends at recorded close or the next strictly later route start.
* CFT eligibility: completed, non-OTP visit with onpremisevisit=1.
  holdorder is internal reference only and does not affect any report metric.
* Qualified call = CFT-eligible visit with positive recorded visit cft achieved.
  JP compliance = distinct scheduled customers with >=1 qualified call / JP count.
  CFT compliance = qualified visit rows / ALL visit rows, including unscheduled,
  incomplete, OTP and LPO visits in the denominator (per requested total visits).
* CFT IN MM and Actual CFT express the same eligible actual minutes: numeric
  minutes and H:MM respectively. Seconds are retained until final formatting.
* Planned CFT = non-OTP completed visit targets (customervisitlog.cft), plus
  unvisited scheduled targets (routesequencecustomerstatus.cft); no master fallback.
  The premises flag excludes actual time and qualification, not planned targets.
* LPO Customer calls = distinct visited customers with customermaster.toplpo=1.
* Productivity follows the application: productive COMPLETED non-LPO visits /
  completed non-LPO visits. Positive nonvoid sales, orders or collections qualify.
  Link transaction headers via customeroperationscontrol.log_id -> visit logkey,
  then routekey + visitkey -> header; EXISTS prevents operation-row multiplication.
* Distinct Order & Invoice Count = distinct source + transactionkey documents
  linked to visits; excludes GRN/return-only invoices, void and nonpositive sales.
  An order and its later invoice remain two documents; no verified conversion key.
* Total Time Spent uses first non-OTP check-in to checkout of the LAST non-OTP
  visit by check-in order. An unfinished last visit yields NULL, not an earlier end.
  The repeated '1st customer' wording for last checkout is treated as a typo.
* End date and odometer distance require routeclosed=1 and valid recorded values.
* Missing/NULL output values return 0, including percentages with no denominator
  and missing formatted dates/times. Existing zero durations remain H:MM.
  No matching journeys returns an empty result set, not an invented journey.
* FSM Name = salesman.salesmanname1 (confirmed by requester).
*/

/* Procedure structure for procedure `sp_salesman_route_status` */

/*!50003 DROP PROCEDURE IF EXISTS `sp_salesman_route_status` */;

DELIMITER $$

/*!50003 CREATE DEFINER=CURRENT_USER PROCEDURE `sp_salesman_route_status`(
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
        SELECT s.*,
            CASE WHEN s.routestartdate IS NOT NULL
                      AND CAST(s.routestartdate AS CHAR) NOT LIKE '0000-%'
                 THEN TIMESTAMP(DATE(s.routestartdate), COALESCE(NULLIF(s.routestarttime, ''), '00:00:00')) END AS start_at,
            CASE WHEN s.routeclosed = 1 AND s.routeenddate IS NOT NULL
                      AND CAST(s.routeenddate AS CHAR) NOT LIKE '0000-%'
                      AND NULLIF(s.routeendtime, '') IS NOT NULL
                 THEN TIMESTAMP(DATE(s.routeenddate), s.routeendtime) END AS close_at
        FROM startendday s
        JOIN routemaster rm ON rm.routecode = s.routecode
        WHERE rm.divisioncode = TRIM(in_divisioncode)
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
        SELECT v.routekey, v.logkey, v.customercode,
            GREATEST(COALESCE(v.cft, 0), 0) AS cft,
            COALESCE(c.toplpo, 0) AS lpo,
            CASE WHEN v.onpremisevisit = 1 THEN 1 ELSE 0 END AS premises_eligible,
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
    documents AS (
        SELECT h.routekey, h.visitkey, h.customercode, h.transactionkey, 'I' AS source
        FROM invoiceheader h
        JOIN journeys j ON j.routekey = h.routekey
        JOIN customermaster c ON c.customercode = h.customercode AND c.activecustomer = 1
        WHERE COALESCE(h.voidflag, 0) = 0 AND h.totalsalesamount > 0 AND h.visitkey > 0
        UNION ALL
        SELECT h.routekey, h.visitkey, h.customercode, h.transactionkey, 'O'
        FROM salesorderheader h
        JOIN journeys j ON j.routekey = h.routekey
        JOIN customermaster c ON c.customercode = h.customercode AND c.activecustomer = 1
        WHERE COALESCE(h.voidflag, 0) = 0 AND h.totalinvoiceamount > 0 AND h.visitkey > 0
        UNION ALL
        SELECT h.routekey, h.visitkey, h.customercode, h.transactionkey, 'C'
        FROM arheader h
        JOIN journeys j ON j.routekey = h.routekey
        JOIN customermaster c ON c.customercode = h.customercode AND c.activecustomer = 1
        WHERE COALESCE(h.voidflag, 0) = 0 AND h.amountpaid > 0 AND h.visitkey > 0
    ),
    visit_documents AS (
        SELECT v.routekey, v.logkey, d.source, d.transactionkey
        FROM visits v
        JOIN documents d ON d.routekey = v.routekey AND d.customercode = v.customercode
        WHERE EXISTS (
            SELECT 1 FROM customeroperationscontrol op
            WHERE op.routekey = v.routekey AND op.log_id = v.logkey
              AND op.customercode = v.customercode AND op.visitkey = d.visitkey
              AND op.log_id > 0 AND op.visitkey > 0
        )
    ),
    productive_visits AS (
        SELECT DISTINCT routekey, logkey FROM visit_documents
    ),
    visit_facts AS (
        SELECT v.*, CASE WHEN p.customercode IS NOT NULL THEN 1 ELSE 0 END AS scheduled,
            CASE WHEN o.logkey IS NOT NULL THEN 1 ELSE 0 END AS otp,
            CASE WHEN v.check_out >= v.check_in THEN 1 ELSE 0 END AS completed,
            CASE WHEN v.check_out >= v.check_in THEN TIMESTAMPDIFF(SECOND, v.check_in, v.check_out) END AS duration_seconds,
            CASE WHEN o.logkey IS NULL AND v.premises_eligible = 1 AND v.cft > 0
                       AND v.check_out >= v.check_in
                       AND TIMESTAMPDIFF(SECOND, v.check_in, v.check_out) >= v.cft * 60
                 THEN 1 ELSE 0 END AS qualified,
            CASE WHEN pv.logkey IS NOT NULL THEN 1 ELSE 0 END AS productive
        FROM visits v
        LEFT JOIN plans p ON p.routekey = v.routekey AND p.customercode = v.customercode
        LEFT JOIN otp_visits o ON o.routekey = v.routekey AND o.logkey = v.logkey
        LEFT JOIN productive_visits pv ON pv.routekey = v.routekey AND pv.logkey = v.logkey
    ),
    visit_totals AS (
        SELECT routekey, COUNT(*) AS calls_made, SUM(1 - scheduled) AS unplanned_calls,
            SUM(qualified) AS qualified_calls,
            COUNT(DISTINCT CASE WHEN scheduled = 1 AND qualified = 1 THEN customercode END) AS qualified_jp,
            COUNT(DISTINCT CASE WHEN lpo = 1 THEN customercode END) AS lpo_customers,
            SUM(CASE WHEN otp = 0 AND premises_eligible = 1 AND completed = 1
                     THEN duration_seconds ELSE 0 END) AS actual_cft_seconds,
            SUM(CASE WHEN otp = 0 AND completed = 1 THEN cft ELSE 0 END) AS visited_planned_minutes,
            SUM(CASE WHEN lpo <> 1 AND completed = 1 THEN 1 ELSE 0 END) AS productivity_base,
            SUM(CASE WHEN lpo <> 1 AND completed = 1 AND productive = 1 THEN 1 ELSE 0 END) AS productive_calls
        FROM visit_facts GROUP BY routekey
    ),
    plan_totals AS (
        SELECT p.routekey, COUNT(*) AS jp_count,
            SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM visits v
                                     WHERE v.routekey = p.routekey AND v.customercode = p.customercode)
                     THEN p.cft ELSE 0 END) AS unvisited_planned_minutes
        FROM plans p GROUP BY p.routekey
    ),
    document_totals AS (
        SELECT routekey, COUNT(DISTINCT source, transactionkey) AS document_count
        FROM visit_documents WHERE source IN ('I', 'O') GROUP BY routekey
    ),
    boundary_visits AS (
        SELECT routekey, logkey, check_in, check_out,
            ROW_NUMBER() OVER (PARTITION BY routekey ORDER BY check_in, logkey) AS first_rank,
            ROW_NUMBER() OVER (PARTITION BY routekey ORDER BY check_in DESC, logkey DESC) AS last_rank
        FROM visit_facts WHERE otp = 0 AND check_in IS NOT NULL
    ),
    boundaries AS (
        SELECT routekey,
            MAX(CASE WHEN first_rank = 1 THEN check_in END) AS first_check_in,
            MAX(CASE WHEN last_rank = 1 AND check_out >= check_in THEN check_out END) AS last_check_out
        FROM boundary_visits GROUP BY routekey
    ),
    report AS (
        SELECT j.routekey, j.routecode, j.salesmancode,
            s.salesmanname1 AS fsm_name,
            CONCAT(COALESCE(NULLIF(s.alternatesalesmancode, ''), CAST(j.salesmancode AS CHAR)),
                   ' - ', COALESCE(s.salesmanname1, '')) AS salesman_code_name,
            j.start_at, j.close_at,
            CASE WHEN j.routeclosed = 1 AND j.routestartodometer >= 0
                      AND j.routeendodometer >= j.routestartodometer
                 THEN j.routeendodometer - j.routestartodometer END AS km,
            COALESCE(pt.jp_count, 0) AS jp_count, COALESCE(vt.calls_made, 0) AS calls_made,
            COALESCE(vt.unplanned_calls, 0) AS unplanned_calls,
            ROUND(100.0 * COALESCE(vt.qualified_jp, 0) / NULLIF(pt.jp_count, 0), 2) AS jp_compliance,
            COALESCE(vt.actual_cft_seconds, 0) AS actual_cft_seconds,
            ROUND(100.0 * COALESCE(vt.qualified_calls, 0) / NULLIF(vt.calls_made, 0), 2) AS cft_compliance,
            COALESCE(dt.document_count, 0) AS document_count,
            ROUND(100.0 * COALESCE(vt.productive_calls, 0) / NULLIF(vt.productivity_base, 0), 2) AS productivity,
            COALESCE(vt.qualified_calls, 0) AS qualified_calls, COALESCE(vt.lpo_customers, 0) AS lpo_customers,
            COALESCE(vt.visited_planned_minutes, 0) + COALESCE(pt.unvisited_planned_minutes, 0) AS planned_minutes,
            b.first_check_in, b.last_check_out,
            TIMESTAMPDIFF(SECOND, b.first_check_in, b.last_check_out) AS total_seconds
        FROM journey_windows j
        LEFT JOIN salesman s ON s.salesmancode = j.salesmancode
        LEFT JOIN visit_totals vt ON vt.routekey = j.routekey
        LEFT JOIN plan_totals pt ON pt.routekey = j.routekey
        LEFT JOIN document_totals dt ON dt.routekey = j.routekey
        LEFT JOIN boundaries b ON b.routekey = j.routekey
    )
    SELECT
        IFNULL(routekey, 0) AS `Route Key`,
        IFNULL(routecode, 0) AS `Route Code`,
        IFNULL(fsm_name, 0) AS `FSM Name`,
        IFNULL(salesman_code_name, 0) AS `Salesman Code - Name`,
        IFNULL(DATE_FORMAT(start_at, '%d/%m/%Y %H:%i:%s'), 0) AS `Route Start Date`,
        IFNULL(DATE_FORMAT(close_at, '%d/%m/%Y %H:%i:%s'), 0) AS `Route End Date`,
        IFNULL(CASE WHEN total_seconds >= 0 THEN CONCAT(FLOOR(total_seconds / 3600), ':',
             LPAD(FLOOR(MOD(total_seconds, 3600) / 60), 2, '0')) END, 0) AS `Total Time Spent`,
        IFNULL(km, 0) AS `Total KM Covered`,
        IFNULL(jp_count, 0) AS `Actual JP count`,
        IFNULL(calls_made, 0) AS `Calls Made`,
        IFNULL(unplanned_calls, 0) AS `UN-Planned Call`,
        IFNULL(jp_compliance, 0) AS `JP COMPLIANCE`,
        IFNULL(ROUND(actual_cft_seconds / 60.0, 2), 0) AS `CFT IN MM`,
        IFNULL(cft_compliance, 0) AS `CFT COMPLIANCE`,
        IFNULL(document_count, 0) AS `Distinct Order & Invoice Count`,
        IFNULL(productivity, 0) AS `Productivity`,
        IFNULL(qualified_calls, 0) AS `Qualified calls`,
        IFNULL(lpo_customers, 0) AS `LPO Customer calls`,
        IFNULL(CONCAT(FLOOR(actual_cft_seconds / 3600), ':',
               LPAD(FLOOR(MOD(actual_cft_seconds, 3600) / 60), 2, '0')), 0) AS `Actual CFT`,
        IFNULL(CONCAT(FLOOR(planned_minutes / 60), ':', LPAD(MOD(planned_minutes, 60), 2, '0')), 0) AS `Planned CFT`,
        IFNULL(DATE_FORMAT(first_check_in, '%d/%m/%Y %H:%i:%s'), 0) AS `First customer checking time`,
        IFNULL(DATE_FORMAT(last_check_out, '%d/%m/%Y %H:%i:%s'), 0) AS `Last customer checkout time`
    FROM report ORDER BY start_at, routecode, routekey;
END */$$

DELIMITER ;
