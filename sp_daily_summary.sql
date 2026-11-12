/*
Daily Summary / Salesman Route Status -- MySQL 8.0+ (CTEs and window functions required).
Schema reference: sfa_ENHANCE_LATEST_20261102.sql.
Select the intended SFA database before installing this file. Installation creates
one procedure; calling it only SELECTs data. No business tables are modified.
The installation script replaces only sp_daily_summary if it exists.
DEFINER=CURRENT_USER uses the installing account; SQL SECURITY INVOKER is retained.

Example (replace DIVISION_CODE with a routemaster.divisioncode value):
  CALL sp_daily_summary('DIVISION_CODE', '2026-10-26', '2026-10-26');

Division is matched to routemaster.divisioncode, linked by startendday.routecode.
Pass ALL (case-insensitive, ignoring surrounding spaces) for all divisions.
Both dates apply to route START date, inclusive; route end date is not filtered.
Overnight visits remain attached to their journey. One result row per routekey,
including open/zero-call journeys, plus one row per division route with no journey
starting anywhere in the selected range. These not-started rows use route-master
salesman details; journey dates and metrics show a dash (-). NULL/blank division and invalid dates are rejected.
This standalone report is not an application authorization endpoint. Any future
application integration must preserve session route/company/geographic restrictions.
SQL SECURITY INVOKER retains the database caller's underlying privileges.

Report definitions / assumptions:
* Only routes with routemaster.activestatus = 1 are included, whether started or not.
* Only active customers are included in plans, visits and transaction sources.
* Actual JP count = distinct active scheduled customers in
  routesequencecustomerstatus for the journey (schelduledflag=1).
  Calls Made = distinct active customers in customervisitlog per journey; repeats
  count once. UN-Planned Call = distinct visited customers absent from that plan.
  Total Visits counts
  every visit record, including repeats. On Premise Visits / Off Premise Visits
  count visit records with onpremisevisit exactly 1 / 0; NULL/other flags are
  included in Total Visits but neither premises count.
* OTP: all types; match route/customer and journey window, then nearest visit
  check-in, tie-breaking by check-in/logkey, as in DashboardMetrics::otp.
  Window ends at recorded close or the next strictly later route start.
  OTP Count counts distinct matched OTP events per journey, not distinct visits;
  multiple events for one visit count separately. Events without a matching
  active-customer visit are not included in this count.
* CFT eligibility: completed, non-OTP visit with onpremisevisit=1.
  holdorder is internal reference only and does not affect any report metric.
* Qualified calls = distinct customers with at least one CFT-eligible visit
  meeting its positive recorded visit target. Separate short revisits are not summed.
  CFT COMPLIANCE = JP actual CFT time / JP planned CFT time * 100,
  rounded to 1 decimal: 100 at plan, below 100 under plan, uncapped above plan.
  Missing/zero planned time returns 0 in the output.
  LPO Customer calls = total visits to active toplpo=1 customers, including repeats.
  Distinct LPO Calls = distinct active toplpo=1 customers visited per journey.
  Actual CFT sums all CFT-eligible visits to JP customers, including repeat visits.
  Planned CFT sums each JP customer's journey-plan target once, including unvisited
  customers. Duplicate plan rows use the maximum nonnegative target per customer.
  Unplanned customers contribute to neither CFT total. Both are displayed as H:MM.
  JP compliance = distinct scheduled customers with >=1 qualified call / JP count.
  JP Compliant Calls exposes that distinct qualifying scheduled-customer count.
* CFT IN MM = total completed on-premises non-OTP JP visit seconds / 60.
  Hold orders do not affect inclusion; visit targets determine qualification.
* CFT Missed = distinct visited customers with at least one completed on-premises,
  non-OTP visit below its positive recorded CFT target, and no qualifying visit
  in the same journey. Includes JP and non-JP; excludes unvisited customers,
  incomplete visits, OTP/off-premises-only visits and missing/nonpositive targets.
* Productivity follows the application: productive COMPLETED non-LPO visits /
  completed non-LPO visits. Positive nonvoid sales, orders or collections qualify.
  Link transaction headers via customeroperationscontrol.log_id -> visit logkey,
  then routekey + visitkey -> header; EXISTS prevents operation-row multiplication.
* Efficiency follows Dashboard: distinct non-LPO customers with a completed
  productive visit / all distinct visited non-LPO customers * 100. Incomplete
  visits remain in its denominator. Sales, orders OR collections qualify via
  existing visit-operation links; premises and OTP do not affect efficiency.
  Productivity and Efficiency percentages round to 1 decimal, as in Dashboard.
  Productive Calls returns the productivity numerator (productive completed
  non-LPO visits, including repeats). Efficiency Count returns the efficiency
  numerator (unique non-LPO customers with any completed productive visit).
* Total Time Spent is elapsed H:MM from the recorded route start to recorded
  route end, including overnight journeys. Requires routeclosed=1 and valid
  timestamps with end >= start; otherwise returns 0.
* End date and odometer distance require routeclosed=1 and valid recorded values.
* Missing/NULL output values return 0, including percentages with no denominator
  and missing formatted dates/times. Existing zero durations remain H:MM.
  Routes without matching journeys retain their six identity fields; all other
  columns show a dash (-). These mixed text/numeric output columns are returned
  as text by MySQL; started-route numeric expressions retain their rounding.
* FSM Name = salesman.arbsalesmanname1, matching the legacy Excel view.
  Salesman Code = alternatesalesmancode (fallback internal salesmancode);
  Salesman Name = salesmanname1. Uses the journey-recorded salesmancode for started
  routes and routemaster.salesmancode for routes without a journey in the range.
  arbroutename comes from routemaster for the journey's routecode.
* Receipt Count = distinct positive, nonvoid arheader collections linked to
  active-customer visits via the existing operation links. Repeated links count
  once per transactionkey per journey; no matching collections returns zero.
* MTD efficiencies use the first day of in_enddate's month through in_enddate,
  scoped by journey start date, per route; repeated on each returned route row.
  in_startdate limits displayed journeys only, not the MTD calculation window.
  Active customers only; LPO customers are excluded from both MTD denominators
  and numerators. A customer planned on ANY MTD journey is JP for that route's
  whole MTD period, even if also visited on an unplanned journey.
  JP Efficiency MTD = COUNT of distinct JP customers with invoice/order.
  Non-JP Efficiency MTD = COUNT of distinct visited non-JP customers with
  invoice/order. These two output columns contain converted customer counts,
  not percentages or lost-opportunity counts. Positive, nonvoid sales/orders qualify;
  return-only invoices and collections do not. Repeats/multiple documents count once.
  These customer-level metrics use header routekey/customercode directly; they
  do not require visit/operation linkage, CFT achievement, premises status or OTP.
  No converted customers returns 0. Each customer counts once in its MTD group.
* Total KM Covered is numeric, rounded to the nearest whole kilometre.
* Decimal outputs have explicit precision below SQL Server's 38-digit limit
  for linked-server OPENQUERY imports; metric rounding remains unchanged.
* Returns the requested Excel fields in the specified report column order.
  First customer checking time / Last customer checkout time are non-OTP
  boundary timestamps. Operation Time is their elapsed H:MM. Last checkout is
  from the last non-OTP visit by check-in order; an unfinished last visit returns 0.
  Do not append KM or percent symbols to metric values.
  No dependency on VW_salesman_routestatus, sp_report_route_strikerate_test,
  sp_salesman_route_status or shared scratch tables. No dynamic SQL or data writes.
  Existing reports/views/procedures are left untouched.
*/

/* Procedure structure for procedure `sp_daily_summary` */

/*!50003 DROP PROCEDURE IF EXISTS `sp_daily_summary` */;

DELIMITER $$

/*!50003 CREATE DEFINER=CURRENT_USER PROCEDURE `sp_daily_summary`(
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
    route_directory AS (
        SELECT rm.routecode AS directory_routecode,
            rm.divisioncode AS directory_divisioncode,
            rm.arbroutename AS directory_location,
            s.arbsalesmanname1 AS directory_fsm_name,
            COALESCE(NULLIF(s.alternatesalesmancode, ''), CAST(rm.salesmancode AS CHAR)) AS directory_salesman_code,
            s.salesmanname1 AS directory_salesman_name
        FROM routemaster rm
        LEFT JOIN salesman s ON s.salesmancode = rm.salesmancode
        WHERE (UPPER(TRIM(in_divisioncode)) = 'ALL' OR rm.divisioncode = TRIM(in_divisioncode))
          AND rm.activestatus = 1
    ),
    journeys AS (
        SELECT s.*, rm.divisioncode AS report_divisioncode,
            rm.arbroutename AS report_arbroutename,
            (SELECT COUNT(DISTINCT jp.customercode)
             FROM routesequencecustomerstatus jp
             JOIN customermaster jc ON jc.customercode = jp.customercode
             WHERE jp.routekey = s.routekey
               AND jp.schelduledflag = 1 AND jc.activecustomer = 1
            ) AS actual_jp_customers,
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
        SELECT v.routekey, v.logkey, v.customercode,
            GREATEST(COALESCE(v.cft, 0), 0) AS cft,
            COALESCE(c.toplpo, 0) AS lpo,
            v.onpremisevisit AS premises_flag,
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
    otp_totals AS (
        SELECT routekey, COUNT(DISTINCT otplogid) AS otp_count
        FROM otp_candidates WHERE candidate_rank = 1
        GROUP BY routekey
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
        SELECT routekey, COUNT(DISTINCT customercode) AS calls_made,
            COUNT(*) AS total_visits,
            SUM(CASE WHEN premises_flag = 1 THEN 1 ELSE 0 END) AS on_premise_visits,
            SUM(CASE WHEN premises_flag = 0 THEN 1 ELSE 0 END) AS off_premise_visits,
            COUNT(DISTINCT CASE WHEN lpo <> 1 THEN customercode END) AS efficiency_base,
            COUNT(DISTINCT CASE WHEN lpo <> 1 AND completed = 1 AND productive = 1
                                THEN customercode END) AS efficient_customers,
            COUNT(DISTINCT CASE WHEN scheduled = 0 THEN customercode END) AS unplanned_calls,
            COUNT(DISTINCT CASE WHEN qualified = 1 THEN customercode END) AS qualified_calls,
            COUNT(DISTINCT CASE WHEN scheduled = 1 AND qualified = 1 THEN customercode END) AS qualified_jp,
            SUM(CASE WHEN lpo = 1 THEN 1 ELSE 0 END) AS lpo_customers,
            COUNT(DISTINCT CASE WHEN lpo = 1 THEN customercode END) AS distinct_lpo_calls,
            SUM(CASE WHEN scheduled = 1 AND otp = 0 AND premises_eligible = 1 AND completed = 1
                     THEN duration_seconds ELSE 0 END) AS actual_cft_seconds,
            SUM(CASE WHEN lpo <> 1 AND completed = 1 THEN 1 ELSE 0 END) AS productivity_base,
            SUM(CASE WHEN lpo <> 1 AND completed = 1 AND productive = 1 THEN 1 ELSE 0 END) AS productive_calls
        FROM visit_facts GROUP BY routekey
    ),
    cft_missed_customers AS (
        SELECT routekey, customercode
        FROM visit_facts
        GROUP BY routekey, customercode
        HAVING MAX(qualified) = 0
           AND MAX(CASE WHEN otp = 0 AND premises_eligible = 1 AND completed = 1
                             AND cft > 0 AND duration_seconds < cft * 60
                        THEN 1 ELSE 0 END) = 1
    ),
    cft_missed_totals AS (
        SELECT routekey, COUNT(*) AS cft_missed
        FROM cft_missed_customers GROUP BY routekey
    ),
    document_totals AS (
        SELECT routekey,
            COUNT(DISTINCT CASE WHEN source IN ('I', 'O') THEN source END,
                           CASE WHEN source IN ('I', 'O') THEN transactionkey END) AS document_count,
            COUNT(DISTINCT CASE WHEN source = 'C' THEN transactionkey END) AS receipt_count
        FROM visit_documents GROUP BY routekey
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
    mtd_journeys AS (
        SELECT m.routekey, m.routecode
        FROM startendday m
        JOIN (SELECT DISTINCT routecode FROM journeys) selected_routes ON selected_routes.routecode = m.routecode
        WHERE m.routestartdate >= DATE_SUB(in_enddate, INTERVAL (DAYOFMONTH(in_enddate) - 1) DAY)
          AND m.routestartdate < DATE_ADD(in_enddate, INTERVAL 1 DAY)
    ),
    mtd_planned_customers AS (
        SELECT DISTINCT m.routecode, p.customercode
        FROM mtd_journeys m
        JOIN routesequencecustomerstatus p ON p.routekey = m.routekey AND p.schelduledflag = 1
        JOIN customermaster c ON c.customercode = p.customercode
            AND c.activecustomer = 1 AND COALESCE(c.toplpo, 0) <> 1
    ),
    mtd_visited_customers AS (
        SELECT DISTINCT m.routecode, v.customercode
        FROM mtd_journeys m
        JOIN customervisitlog v ON v.routekey = m.routekey
        JOIN customermaster c ON c.customercode = v.customercode
            AND c.activecustomer = 1 AND COALESCE(c.toplpo, 0) <> 1
    ),
    mtd_document_customers AS (
        SELECT m.routecode, h.customercode
        FROM mtd_journeys m
        JOIN invoiceheader h ON h.routekey = m.routekey
        JOIN customermaster c ON c.customercode = h.customercode
            AND c.activecustomer = 1 AND COALESCE(c.toplpo, 0) <> 1
        WHERE COALESCE(h.voidflag, 0) = 0 AND h.totalsalesamount > 0
        UNION
        SELECT m.routecode, h.customercode
        FROM mtd_journeys m
        JOIN salesorderheader h ON h.routekey = m.routekey
        JOIN customermaster c ON c.customercode = h.customercode
            AND c.activecustomer = 1 AND COALESCE(c.toplpo, 0) <> 1
        WHERE COALESCE(h.voidflag, 0) = 0 AND h.totalinvoiceamount > 0
    ),
    mtd_customer_base AS (
        SELECT p.routecode, p.customercode, 1 AS is_jp FROM mtd_planned_customers p
        UNION ALL
        SELECT v.routecode, v.customercode, 0 AS is_jp FROM mtd_visited_customers v
        WHERE NOT EXISTS (
            SELECT 1 FROM mtd_planned_customers p
            WHERE p.routecode = v.routecode AND p.customercode = v.customercode
        )
    ),
    mtd_efficiency AS (
        SELECT b.routecode,
            COUNT(DISTINCT CASE WHEN b.is_jp = 1 AND d.customercode IS NOT NULL
                                THEN b.customercode END) AS jp_efficiency_mtd,
            COUNT(DISTINCT CASE WHEN b.is_jp = 0 AND d.customercode IS NOT NULL
                                THEN b.customercode END) AS non_jp_efficiency_mtd
        FROM mtd_customer_base b
        LEFT JOIN mtd_document_customers d ON d.routecode = b.routecode AND d.customercode = b.customercode
        GROUP BY b.routecode
    ),
    report AS (
        SELECT j.routekey, j.routecode, j.salesmancode, j.report_divisioncode, j.report_arbroutename,
            s.arbsalesmanname1 AS fsm_name,
            COALESCE(NULLIF(s.alternatesalesmancode, ''), CAST(j.salesmancode AS CHAR)) AS salesman_code,
            s.salesmanname1 AS salesman_name,
            j.start_at, j.close_at,
            TIMESTAMPDIFF(SECOND, j.start_at, j.close_at) AS route_duration_seconds,
            CASE WHEN j.routeclosed = 1 AND j.routestartodometer >= 0
                      AND j.routeendodometer >= j.routestartodometer
                 THEN j.routeendodometer - j.routestartodometer END AS km,
            j.actual_jp_customers AS jp_count, COALESCE(vt.calls_made, 0) AS calls_made,
            COALESCE(vt.total_visits, 0) AS total_visits,
            COALESCE(vt.on_premise_visits, 0) AS on_premise_visits,
            COALESCE(vt.off_premise_visits, 0) AS off_premise_visits,
            COALESCE(ot.otp_count, 0) AS otp_count,
            COALESCE(vt.unplanned_calls, 0) AS unplanned_calls,
            COALESCE(vt.qualified_jp, 0) AS jp_compliant_calls,
            ROUND(100.0 * COALESCE(vt.qualified_jp, 0) / NULLIF(j.actual_jp_customers, 0), 2) AS jp_compliance,
            COALESCE(vt.actual_cft_seconds, 0) AS actual_cft_seconds,
            COALESCE(dt.document_count, 0) AS document_count,
            COALESCE(dt.receipt_count, 0) AS receipt_count,
            COALESCE(vt.productive_calls, 0) AS productive_calls,
            COALESCE(vt.efficient_customers, 0) AS efficient_customers,
            ROUND(100.0 * COALESCE(vt.productive_calls, 0) / NULLIF(vt.productivity_base, 0), 1) AS productivity,
            ROUND(100.0 * COALESCE(vt.efficient_customers, 0) / NULLIF(vt.efficiency_base, 0), 1) AS efficiency,
            COALESCE(vt.qualified_calls, 0) AS qualified_calls, COALESCE(vt.lpo_customers, 0) AS lpo_customers,
            COALESCE(vt.distinct_lpo_calls, 0) AS distinct_lpo_calls,
            -- Read original JP targets directly, independent of the reused plans CTE.
            COALESCE((
                SELECT SUM(jp_targets.cft)
                FROM (
                    SELECT p.routekey, p.customercode,
                        MAX(GREATEST(COALESCE(p.cft, 0), 0)) AS cft
                    FROM routesequencecustomerstatus p
                    JOIN customermaster c ON c.customercode = p.customercode
                        AND c.activecustomer = 1
                    WHERE p.schelduledflag = 1
                    GROUP BY p.routekey, p.customercode
                ) AS jp_targets
                WHERE jp_targets.routekey = j.routekey
            ), 0) AS planned_minutes,
            me.jp_efficiency_mtd, me.non_jp_efficiency_mtd,
            COALESCE(cm.cft_missed, 0) AS cft_missed,
            b.first_check_in, b.last_check_out,
            TIMESTAMPDIFF(SECOND, b.first_check_in, b.last_check_out) AS total_seconds
        FROM journey_windows j
        LEFT JOIN salesman s ON s.salesmancode = j.salesmancode
        LEFT JOIN visit_totals vt ON vt.routekey = j.routekey
        LEFT JOIN otp_totals ot ON ot.routekey = j.routekey
        LEFT JOIN document_totals dt ON dt.routekey = j.routekey
        LEFT JOIN boundaries b ON b.routekey = j.routekey
        LEFT JOIN mtd_efficiency me ON me.routecode = j.routecode
        LEFT JOIN cft_missed_totals cm ON cm.routekey = j.routekey
    )
    SELECT
        IFNULL(directory_divisioncode, 0) AS `Division Code`,
        IFNULL(directory_routecode, 0) AS `Route Code`,
        IFNULL(REPLACE(directory_location, 'Collection Bank', ''), 0) AS `Location`,
        IFNULL(CASE WHEN routekey IS NULL THEN directory_fsm_name ELSE fsm_name END, 0) AS `FSM Name`,
        IFNULL(CASE WHEN routekey IS NULL THEN directory_salesman_code ELSE salesman_code END, 0) AS `Salesman Code`,
        IFNULL(CASE WHEN routekey IS NULL THEN directory_salesman_name ELSE salesman_name END, 0) AS `Salesman Name`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(DATE_FORMAT(start_at, '%d/%m/%Y %H:%i:%s'), 0) END AS `Route Start Date`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(DATE_FORMAT(close_at, '%d/%m/%Y %H:%i:%s'), 0) END AS `Route End Date`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(CASE WHEN route_duration_seconds >= 0 THEN CONCAT(FLOOR(route_duration_seconds / 3600), ':',
             LPAD(FLOOR(MOD(route_duration_seconds, 3600) / 60), 2, '0')) END, 0) END AS `Total Time Spent`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(DATE_FORMAT(first_check_in, '%d/%m/%Y %H:%i:%s'), 0) END AS `First customer check-in time`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(DATE_FORMAT(last_check_out, '%d/%m/%Y %H:%i:%s'), 0) END AS `Last customer check-out time`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(CASE WHEN total_seconds >= 0 THEN CONCAT(FLOOR(total_seconds / 3600), ':',
             LPAD(FLOOR(MOD(total_seconds, 3600) / 60), 2, '0')) END, 0) END AS `Operation Time`,
        CASE WHEN routekey IS NULL THEN '-' ELSE CAST(IFNULL(km, 0) AS DECIMAL(18, 0)) END AS `Total KM Covered`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(jp_count, 0) END AS `Actual JP count`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(unplanned_calls, 0) END AS `UN-Planned Call`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(calls_made, 0) END AS `Calls Made`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(cft_missed, 0) END AS `CFT Missed`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(qualified_calls, 0) END AS `Qualified calls-All`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(jp_compliant_calls, 0) END AS `JP Compliant Calls`,
        CASE WHEN routekey IS NULL THEN '-' ELSE CAST(IFNULL(jp_compliance, 0) AS DECIMAL(7, 2)) END AS `JP COMPLIANCE`,
        CASE WHEN routekey IS NULL THEN '-' ELSE CAST(IFNULL(ROUND(actual_cft_seconds / 60.0, 0), 0) AS DECIMAL(18, 0)) END AS `Actual CFT IN MM`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(CONCAT(FLOOR(actual_cft_seconds / 3600), ':',
               LPAD(FLOOR(MOD(actual_cft_seconds, 3600) / 60), 2, '0')), 0) END AS `Actual CFT IN HH:MM`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(CONCAT(FLOOR(planned_minutes / 60), ':', LPAD(MOD(planned_minutes, 60), 2, '0')), 0) END AS `Planned CFT`,
        CASE WHEN routekey IS NULL THEN '-' ELSE CAST(IFNULL(ROUND(100.0 * (actual_cft_seconds / 60.0)
            / NULLIF(planned_minutes, 0), 1), 0) AS DECIMAL(7, 1)) END AS `CFT COMPLIANCE`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(total_visits, 0) END AS `Total Visits`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(on_premise_visits, 0) END AS `On Premise Visits`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(off_premise_visits, 0) END AS `Off Premise Visits`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(otp_count, 0) END AS `OTP Count`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(document_count, 0) END AS `Distinct Order & Invoice Count`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(receipt_count, 0) END AS `Receipt Count`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(lpo_customers, 0) END AS `LPO Customer calls`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(distinct_lpo_calls, 0) END AS `Distinct LPO Calls`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(productive_calls, 0) END AS `Productive Calls`,
        CASE WHEN routekey IS NULL THEN '-' ELSE CAST(IFNULL(productivity, 0) AS DECIMAL(7, 1)) END AS `Productivity`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(efficient_customers, 0) END AS `Efficiency Count`,
        CASE WHEN routekey IS NULL THEN '-' ELSE CAST(IFNULL(efficiency, 0) AS DECIMAL(7, 1)) END AS `Efficiency`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(jp_efficiency_mtd, 0) END AS `JP Efficiency MTD`,
        CASE WHEN routekey IS NULL THEN '-' ELSE IFNULL(non_jp_efficiency_mtd, 0) END AS `Non-JP Efficiency MTD`
    FROM route_directory rd
    LEFT JOIN report ON report.routecode = rd.directory_routecode
    ORDER BY start_at, directory_routecode, routekey;
END */$$

DELIMITER ;
