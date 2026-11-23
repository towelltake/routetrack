/*
Route activity detail for MySQL 8.0+.
CALL sp_route_activity_report('ALL', '2026-11-01', '2026-11-16');
Division and inclusive journey-start dates follow sp_cft_report.
One row per non-void document, plus one NON SERVICED row for a
non-void activity with no non-void documents. Recorded no-sale documents
retain their document numbers. Activity timing and planned CFT can repeat
across documents. Planned CFT is in minutes. Missing customer visit times show
OFF PRIMISE. Missing document numbers and unavailable customer face durations
display as 0. Missing activity times and unavailable activity durations are NULL.
Calls use the database caller's privileges.
*/
DROP PROCEDURE IF EXISTS `sp_route_activity_report`;

DELIMITER $$

CREATE PROCEDURE `sp_route_activity_report`(
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

    WITH journeys AS (
        SELECT s.routekey, s.routecode, s.salesmancode, rm.divisioncode, s.routestartdate
        FROM startendday s
        JOIN routemaster rm ON rm.routecode = s.routecode
        WHERE rm.activestatus = 1
          AND (UPPER(TRIM(in_divisioncode)) = 'ALL' OR rm.divisioncode = TRIM(in_divisioncode))
          AND s.routestartdate >= in_startdate
          AND s.routestartdate < DATE_ADD(in_enddate, INTERVAL 1 DAY)
    ),
    plans AS (
        SELECT p.routekey, p.customercode, MAX(GREATEST(COALESCE(p.cft, 0), 0)) AS cft
        FROM routesequencecustomerstatus p
        JOIN journeys j ON j.routekey = p.routekey
        WHERE p.schelduledflag = 1
        GROUP BY p.routekey, p.customercode
    ),
    operations AS (
        SELECT o.routekey, o.visitkey, o.customercode, o.log_id, o.reasoncode, o.visitstartdate,
            CASE WHEN CAST(o.visitstartdate AS CHAR) NOT LIKE '0000-%'
                 THEN CAST(CONCAT(DATE(o.visitstartdate), ' ', NULLIF(TRIM(o.visitstarttime), '')) AS DATETIME) END AS activity_start,
            CASE WHEN CAST(o.visitenddate AS CHAR) NOT LIKE '0000-%'
                 THEN CAST(CONCAT(DATE(o.visitenddate), ' ', NULLIF(TRIM(o.visitendtime), '')) AS DATETIME) END AS activity_end
        FROM customeroperationscontrol o
        JOIN journeys j ON j.routekey = o.routekey
        WHERE o.voidflag = 0
    ),
    documents AS (
        SELECT i.routekey, i.visitkey, i.customercode, i.documentnumber, DATE(i.transactiondate) AS transaction_date,
            'INVOICE' AS transaction_type, NULL AS reason_code,
            CAST(COALESCE(i.totalinvoiceamount, 0) AS DECIMAL(19,4)) AS transaction_amount,
            COALESCE(i.amountpaid, 0) AS amount_collected, 0 AS receipt_collected
        FROM invoiceheader i
        JOIN journeys j ON j.routekey = i.routekey
        WHERE COALESCE(i.voidflag, 0) = 0

        UNION ALL

        SELECT o.routekey, o.visitkey, o.customercode, o.documentnumber, DATE(o.transactiondate),
            'ORDER', NULL, CAST(COALESCE(o.totalinvoiceamount, 0) AS DECIMAL(19,4)), 0, 0
        FROM salesorderheader o
        JOIN journeys j ON j.routekey = o.routekey
        WHERE COALESCE(o.voidflag, 0) = 0

        UNION ALL

        SELECT a.routekey, a.visitkey, a.customercode, a.documentnumber, DATE(a.transactiondate),
            'RECEIPT', NULL, 0, 0, COALESCE(a.amountpaid, 0)
        FROM arheader a
        JOIN journeys j ON j.routekey = a.routekey
        WHERE COALESCE(a.voidflag, 0) = 0

        UNION ALL

        SELECT n.routekey, n.visitkey, n.customercode, n.documentnumber, DATE(n.transactiondate),
            'NON SERVICED', NULLIF(n.nosalereasoncode, 0), 0, 0, 0
        FROM nosalesheader n
        JOIN journeys j ON j.routekey = n.routekey
        WHERE COALESCE(n.voidflag, 0) = 0
    ),
    report_rows AS (
        SELECT * FROM documents

        UNION ALL

        SELECT o.routekey, o.visitkey, o.customercode, NULL,
            CASE WHEN CAST(o.visitstartdate AS CHAR) NOT LIKE '0000-%' THEN DATE(o.visitstartdate) END,
            'NON SERVICED', o.reasoncode, 0, 0, 0
        FROM operations o
        WHERE NOT EXISTS (
            SELECT 1 FROM documents d WHERE d.routekey = o.routekey AND d.visitkey = o.visitkey
        )
    ),
    timing AS (
        SELECT r.*, c.customername, o.activity_start, o.activity_end,
            COALESCE(p.cft, 0) AS planned_cft,
            CASE WHEN CAST(v.logstartdate AS CHAR) NOT LIKE '0000-%'
                 THEN TIMESTAMP(v.logstartdate, v.logstarttime) END AS visit_start,
            CASE WHEN CAST(v.logenddate AS CHAR) NOT LIKE '0000-%'
                 THEN TIMESTAMP(v.logenddate, v.logendtime) END AS visit_end,
            COALESCE(r.reason_code, NULLIF(o.reasoncode, 0)) AS non_service_reason
        FROM report_rows r
        LEFT JOIN operations o ON o.routekey = r.routekey AND o.visitkey = r.visitkey
        JOIN customermaster c ON c.customercode = COALESCE(r.customercode, o.customercode)
            AND c.activecustomer = 1
        LEFT JOIN customervisitlog v ON v.routekey = o.routekey AND v.logkey = o.log_id
            AND v.customercode = c.customercode
        LEFT JOIN plans p ON p.routekey = r.routekey AND p.customercode = c.customercode
    ),
    durations AS (
        SELECT t.*,
            CASE WHEN t.visit_end >= t.visit_start
                 THEN TIMESTAMPDIFF(MINUTE, t.visit_start, t.visit_end) END AS visit_minutes,
            CASE WHEN t.activity_end >= t.activity_start
                 THEN TIMESTAMPDIFF(MINUTE, t.activity_start, t.activity_end) END AS activity_minutes
        FROM timing t
    )
    SELECT j.divisioncode AS `Division`,
        j.routecode AS `Route`,
        s.arbsalesmanname1 AS `FSM Name`,
        s.salesmanname1 AS `Salesman Name`,
        COALESCE(d.transaction_date, DATE(j.routestartdate)) AS `Transaction Date`,
        COALESCE(d.documentnumber, 0) AS `Document Number`,
        d.customername AS `Customer`,
        COALESCE(DATE_FORMAT(d.visit_start, '%H:%i:%s'), 'OFF PRIMISE') AS `Cust_Visit_Start_Time`,
        COALESCE(DATE_FORMAT(d.visit_end, '%H:%i:%s'), 'OFF PRIMISE') AS `Cust_Visit_End_Time`,
        COALESCE(CONCAT(IF(d.visit_minutes < 600, '0', ''), d.visit_minutes DIV 60,
            ':', LPAD(MOD(d.visit_minutes, 60), 2, '0')), '0') AS `Cust_Face_Duration_HH:MM`,
        CAST(d.planned_cft AS DECIMAL(18,2)) AS `Planned_CFT`,
        TIME(d.activity_start) AS `Activity_Start_Time`,
        TIME(d.activity_end) AS `Activity_End_Time`,
        CONCAT(IF(d.activity_minutes < 600, '0', ''), d.activity_minutes DIV 60,
            ':', LPAD(MOD(d.activity_minutes, 60), 2, '0')) AS `Activity_Duration_HH:MM`,
        d.transaction_type AS `Transaction`,
        CASE WHEN d.transaction_type = 'NON SERVICED'
             THEN COALESCE(n.description, '')
             ELSE 'VISITED' END AS `Reason`,
        d.transaction_amount AS `Transaction Amount`,
        d.amount_collected AS `Amount Collected`,
        d.receipt_collected AS `Receipt Collected`
    FROM durations d
    JOIN journeys j ON j.routekey = d.routekey
    LEFT JOIN salesman s ON s.salesmancode = j.salesmancode
    LEFT JOIN nonservreasons n ON n.code = d.non_service_reason AND d.transaction_type = 'NON SERVICED'
    ORDER BY j.routestartdate, j.routecode, j.routekey, d.activity_start, d.visitkey,
        d.transaction_date, d.transaction_type, d.documentnumber;
END$$

DELIMITER ;
