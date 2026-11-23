/*
Salesman route activity summary for MySQL 8.0+.
CALL sp_mtd_route_activity_report('ALL', '2026-11-01', '2026-11-16');

One row per division and journey salesman with activity in the inclusive
journey-start range. Division accepts ALL. Request the first of the month
as the start date for month-to-date totals across all columns.
Journey metrics follow sp_daily_summary. Counts and durations sum across
journeys; percentages divide summed numerators by summed denominators.
The two MTD efficiency counts use the end date's month through the end date,
regardless of the start date. Within each division/salesman, a customer planned
on any MTD journey is JP; converted customers are counted once across routes.
FSM Name uses salesman.arbsalesmanname1. This standalone report uses the
database caller's privileges and only reads data when called.
*/
DROP PROCEDURE IF EXISTS `sp_mtd_route_activity_report`;

DELIMITER $$

CREATE PROCEDURE `sp_mtd_route_activity_report`(
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
        SELECT s.routekey, s.routecode, s.salesmancode, s.routeclosed,
            s.routestartodometer, s.routeendodometer, rm.divisioncode,
            CASE WHEN s.routestartdate IS NOT NULL
                      AND CAST(s.routestartdate AS CHAR) NOT LIKE '0000-%'
                 THEN CAST(CONCAT(DATE(s.routestartdate), ' ', COALESCE(NULLIF(s.routestarttime, ''), '00:00:00')) AS DATETIME) END AS start_at,
            CASE WHEN s.routeclosed = 1 AND s.routeenddate IS NOT NULL
                      AND CAST(s.routeenddate AS CHAR) NOT LIKE '0000-%'
                      AND NULLIF(s.routeendtime, '') IS NOT NULL
                 THEN CAST(CONCAT(DATE(s.routeenddate), ' ', s.routeendtime) AS DATETIME) END AS close_at
        FROM startendday s
        JOIN routemaster rm ON rm.routecode = s.routecode
        WHERE (UPPER(TRIM(in_divisioncode)) = 'ALL' OR rm.divisioncode = TRIM(in_divisioncode))
          AND rm.activestatus = 1
          AND s.routestartdate >= in_startdate
          AND s.routestartdate < DATE_ADD(in_enddate, INTERVAL 1 DAY)
    ),
    journey_windows AS (
        SELECT j.*,
            (SELECT MIN(CAST(CONCAT(DATE(n.routestartdate), ' ', COALESCE(NULLIF(n.routestarttime, ''), '00:00:00')) AS DATETIME))
             FROM startendday n
             WHERE n.routecode = j.routecode
               AND CAST(CONCAT(DATE(n.routestartdate), ' ', COALESCE(NULLIF(n.routestarttime, ''), '00:00:00')) AS DATETIME) > j.start_at
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
                ABS(TIMESTAMPDIFF(SECOND, v.check_in, CAST(CONCAT(DATE(o.otpdate), ' ', o.otptime) AS DATETIME))),
                v.check_in, v.logkey) AS candidate_rank
        FROM otplogdetail o
        JOIN journey_windows j ON j.routecode = o.routecode
            AND CAST(CONCAT(DATE(o.otpdate), ' ', o.otptime) AS DATETIME) >= j.start_at
            AND (j.close_at IS NULL OR CAST(CONCAT(DATE(o.otpdate), ' ', o.otptime) AS DATETIME) <= j.close_at)
            AND (j.next_start_at IS NULL OR CAST(CONCAT(DATE(o.otpdate), ' ', o.otptime) AS DATETIME) < j.next_start_at)
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
    plan_totals AS (
        SELECT routekey, COUNT(*) AS jp_count, SUM(cft) AS planned_minutes
        FROM plans GROUP BY routekey
    ),
    salesman_totals AS (
        SELECT j.divisioncode, j.salesmancode,
            SUM(CASE WHEN j.close_at >= j.start_at
                     THEN TIMESTAMPDIFF(SECOND, j.start_at, j.close_at) ELSE 0 END) AS elapsed_seconds,
            SUM(CASE WHEN b.last_check_out >= b.first_check_in
                     THEN TIMESTAMPDIFF(SECOND, b.first_check_in, b.last_check_out) ELSE 0 END) AS operation_seconds,
            SUM(CASE WHEN j.routeclosed = 1 AND j.routestartodometer >= 0
                          AND j.routeendodometer >= j.routestartodometer
                     THEN j.routeendodometer - j.routestartodometer ELSE 0 END) AS km,
            SUM(COALESCE(p.jp_count, 0)) AS jp_count,
            SUM(COALESCE(v.unplanned_calls, 0)) AS unplanned_calls,
            SUM(COALESCE(v.calls_made, 0)) AS calls_made,
            SUM(COALESCE(cm.cft_missed, 0)) AS cft_missed,
            SUM(COALESCE(v.qualified_calls, 0)) AS qualified_calls,
            SUM(COALESCE(v.qualified_jp, 0)) AS qualified_jp,
            SUM(COALESCE(v.actual_cft_seconds, 0)) AS actual_cft_seconds,
            SUM(COALESCE(p.planned_minutes, 0)) AS planned_minutes,
            SUM(COALESCE(v.total_visits, 0)) AS total_visits,
            SUM(COALESCE(v.on_premise_visits, 0)) AS on_premise_visits,
            SUM(COALESCE(v.off_premise_visits, 0)) AS off_premise_visits,
            SUM(COALESCE(o.otp_count, 0)) AS otp_count,
            SUM(COALESCE(d.document_count, 0)) AS document_count,
            SUM(COALESCE(d.receipt_count, 0)) AS receipt_count,
            SUM(COALESCE(v.lpo_customers, 0)) AS lpo_customers,
            SUM(COALESCE(v.productive_calls, 0)) AS productive_calls,
            SUM(COALESCE(v.productivity_base, 0)) AS productivity_base,
            SUM(COALESCE(v.efficient_customers, 0)) AS efficient_customers,
            SUM(COALESCE(v.efficiency_base, 0)) AS efficiency_base
        FROM journeys j
        LEFT JOIN plan_totals p ON p.routekey = j.routekey
        LEFT JOIN visit_totals v ON v.routekey = j.routekey
        LEFT JOIN cft_missed_totals cm ON cm.routekey = j.routekey
        LEFT JOIN otp_totals o ON o.routekey = j.routekey
        LEFT JOIN document_totals d ON d.routekey = j.routekey
        LEFT JOIN boundaries b ON b.routekey = j.routekey
        GROUP BY j.divisioncode, j.salesmancode
    ),
    mtd_journeys AS (
        SELECT m.routekey, r.divisioncode, m.salesmancode
        FROM startendday m
        JOIN routemaster r ON r.routecode = m.routecode AND r.activestatus = 1
        JOIN salesman_totals t ON t.divisioncode <=> r.divisioncode AND t.salesmancode <=> m.salesmancode
        WHERE m.routestartdate >= DATE_SUB(in_enddate, INTERVAL (DAYOFMONTH(in_enddate) - 1) DAY)
          AND m.routestartdate < DATE_ADD(in_enddate, INTERVAL 1 DAY)
    ),
    mtd_customer_events AS (
        SELECT m.divisioncode, m.salesmancode, p.customercode, 1 AS planned, 0 AS visited, 0 AS converted
        FROM mtd_journeys m
        JOIN routesequencecustomerstatus p ON p.routekey = m.routekey AND p.schelduledflag = 1
        UNION ALL
        SELECT m.divisioncode, m.salesmancode, v.customercode, 0, 1, 0
        FROM mtd_journeys m
        JOIN customervisitlog v ON v.routekey = m.routekey
        UNION ALL
        SELECT m.divisioncode, m.salesmancode, h.customercode, 0, 0, 1
        FROM mtd_journeys m
        JOIN invoiceheader h ON h.routekey = m.routekey
        WHERE COALESCE(h.voidflag, 0) = 0 AND h.totalsalesamount > 0
        UNION ALL
        SELECT m.divisioncode, m.salesmancode, h.customercode, 0, 0, 1
        FROM mtd_journeys m
        JOIN salesorderheader h ON h.routekey = m.routekey
        WHERE COALESCE(h.voidflag, 0) = 0 AND h.totalinvoiceamount > 0
    ),
    mtd_customers AS (
        SELECT e.divisioncode, e.salesmancode, e.customercode,
            MAX(e.planned) AS planned, MAX(e.visited) AS visited, MAX(e.converted) AS converted
        FROM mtd_customer_events e
        JOIN customermaster c ON c.customercode = e.customercode
            AND c.activecustomer = 1 AND COALESCE(c.toplpo, 0) <> 1
        GROUP BY e.divisioncode, e.salesmancode, e.customercode
    ),
    mtd_efficiency AS (
        SELECT divisioncode, salesmancode,
            COUNT(CASE WHEN planned = 1 AND converted = 1 THEN 1 END) AS jp_efficiency_mtd,
            COUNT(CASE WHEN planned = 0 AND visited = 1 AND converted = 1 THEN 1 END) AS non_jp_efficiency_mtd
        FROM mtd_customers
        GROUP BY divisioncode, salesmancode
    )
    SELECT COALESCE(t.divisioncode, '0') AS `Division Code`,
        COALESCE(s.arbsalesmanname1, '0') AS `FSM Name`,
        COALESCE(NULLIF(s.alternatesalesmancode, ''), CAST(t.salesmancode AS CHAR), '0') AS `Salesman Code`,
        COALESCE(s.salesmanname1, '0') AS `Salesman Name`,
        CONCAT(t.elapsed_seconds DIV 3600, ':', LPAD(MOD(t.elapsed_seconds DIV 60, 60), 2, '0')) AS `Total Time Spent`,
        CONCAT(t.operation_seconds DIV 3600, ':', LPAD(MOD(t.operation_seconds DIV 60, 60), 2, '0')) AS `Operation Time`,
        CAST(t.km AS DECIMAL(18,0)) AS `Total KM Covered`,
        CAST(t.jp_count AS DECIMAL(18,0)) AS `Actual JP Count`,
        CAST(t.unplanned_calls AS DECIMAL(18,0)) AS `Unplanned Calls`,
        CAST(t.calls_made AS DECIMAL(18,0)) AS `Calls Made`,
        CAST(t.cft_missed AS DECIMAL(18,0)) AS `CFT Missed`,
        CAST(t.qualified_calls AS DECIMAL(18,0)) AS `Qualified Calls - All`,
        CAST(t.qualified_jp AS DECIMAL(18,0)) AS `JP Compliant Calls`,
        CAST(COALESCE(100.0 * t.qualified_jp / NULLIF(t.jp_count, 0), 0) AS DECIMAL(7,2)) AS `JP Compliance`,
        CAST(ROUND(t.actual_cft_seconds / 60.0, 0) AS DECIMAL(18,0)) AS `Actual CFT in MM`,
        CONCAT(t.actual_cft_seconds DIV 3600, ':', LPAD(MOD(t.actual_cft_seconds DIV 60, 60), 2, '0')) AS `Actual CFT in HH:MM`,
        CONCAT(t.planned_minutes DIV 60, ':', LPAD(MOD(t.planned_minutes, 60), 2, '0')) AS `Planned CFT`,
        CAST(COALESCE(ROUND(100.0 * (t.actual_cft_seconds / 60.0)
            / NULLIF(t.planned_minutes, 0), 1), 0) AS DECIMAL(18,1)) AS `CFT Compliance`,
        CAST(t.total_visits AS DECIMAL(18,0)) AS `Total Visits`,
        CAST(t.on_premise_visits AS DECIMAL(18,0)) AS `On Premise Visits`,
        CAST(t.off_premise_visits AS DECIMAL(18,0)) AS `Off Premise Visits`,
        CAST(t.otp_count AS DECIMAL(18,0)) AS `OTP Count`,
        CAST(t.document_count AS DECIMAL(18,0)) AS `Distinct Order & Invoice Count`,
        CAST(t.receipt_count AS DECIMAL(18,0)) AS `Receipt Count`,
        CAST(t.lpo_customers AS DECIMAL(18,0)) AS `LPO Customer Calls`,
        CAST(t.productive_calls AS DECIMAL(18,0)) AS `Productive Calls`,
        CAST(COALESCE(100.0 * t.productive_calls / NULLIF(t.productivity_base, 0), 0) AS DECIMAL(7,1)) AS `Productivity`,
        CAST(t.efficient_customers AS DECIMAL(18,0)) AS `Efficiency Count`,
        CAST(COALESCE(100.0 * t.efficient_customers / NULLIF(t.efficiency_base, 0), 0) AS DECIMAL(7,1)) AS `Efficiency`,
        COALESCE(m.jp_efficiency_mtd, 0) AS `JP Efficiency MTD`,
        COALESCE(m.non_jp_efficiency_mtd, 0) AS `Non-JP Efficiency MTD`
    FROM salesman_totals t
    LEFT JOIN salesman s ON s.salesmancode = t.salesmancode
    LEFT JOIN mtd_efficiency m ON m.divisioncode <=> t.divisioncode AND m.salesmancode <=> t.salesmancode
    ORDER BY t.divisioncode, t.salesmancode;
END$$

DELIMITER ;
