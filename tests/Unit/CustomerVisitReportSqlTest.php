<?php

// Execute the report SELECT against synthetic data with SQLite equivalents for
// MySQL scalar functions. This does not validate MySQL procedure installation.
function customerVisitReportRows(PDO $db, string $division = 'ALL', string $from = '2026-11-01', string $to = '2026-11-01'): array
{
    $sql = file_get_contents(__DIR__.'/../../sp_customer_visit_report.sql');
    $sql = substr($sql, strpos($sql, "    WITH\n"));
    $sql = substr($sql, 0, strpos($sql, 'END */$$'));
    $sql = str_replace('DATE_ADD(in_enddate, INTERVAL 1 DAY)', "DATE(in_enddate, '+1 day')", $sql);
    $sql = str_replace('TIMESTAMPDIFF(SECOND,', "TIMESTAMPDIFF('SECOND',", $sql);
    $sql = preg_replace('/\bin_(divisioncode|startdate|enddate)\b/', ':$1', $sql);
    $query = $db->prepare($sql);
    $query->execute(['divisioncode' => $division, 'startdate' => $from, 'enddate' => $to]);

    return $query->fetchAll(PDO::FETCH_ASSOC);
}

beforeEach(function () {
    $this->customerVisitDb = new PDO('sqlite::memory:');
    $db = $this->customerVisitDb;
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->sqliteCreateFunction('TIMESTAMP', fn ($date, $time) => $date === null || $time === null ? null : substr($date, 0, 10).' '.$time, 2);
    $db->sqliteCreateFunction('TIMESTAMPDIFF', fn ($unit, $start, $end) => $start === null || $end === null ? null : strtotime($end) - strtotime($start), 3);
    $db->sqliteCreateFunction('GREATEST', fn ($a, $b) => max($a, $b), 2);
    $db->sqliteCreateFunction('FLOOR', fn ($v) => $v === null ? null : floor($v), 1);
    $db->sqliteCreateFunction('MOD', fn ($a, $b) => $a === null ? null : $a % $b, 2);
    $db->sqliteCreateFunction('CONCAT', fn (...$parts) => in_array(null, $parts, true) ? null : implode('', $parts));
    $db->sqliteCreateFunction('LPAD', fn ($v, $length, $pad) => $v === null ? null : str_pad((string) $v, $length, $pad, STR_PAD_LEFT), 3);
    $db->sqliteCreateFunction('DATE_FORMAT', fn ($v, $format) => $v === null ? null : date($format === '%d/%m/%Y' ? 'd/m/Y' : 'H:i:s', strtotime($v)), 2);
    foreach ([
        'routemaster (routecode integer, divisioncode text, activestatus integer)',
        'startendday (routekey integer, routecode integer, salesmancode integer, routestartdate text, routestarttime text, routeclosed integer, routeenddate text, routeendtime text)',
        'salesman (salesmancode integer, alternatesalesmancode text, salesmanname1 text)',
        'customermaster (customercode integer, alternatecode text, customername text, activecustomer integer)',
        'customervisitlog (routekey integer, logkey integer, customercode integer, onpremisevisit integer, logstartdate text, logstarttime text, logenddate text, logendtime text)',
        'routesequencecustomerstatus (routekey integer, customercode integer, schelduledflag integer, cft real)',
        'otplogdetail (otplogid integer, routecode integer, customercode integer, otpdate text, otptime text)',
    ] as $table) $db->exec('CREATE TABLE '.$table);
    $db->exec('ALTER TABLE customervisitlog ADD COLUMN cft real DEFAULT 10');
    $db->exec("INSERT INTO routemaster VALUES (1,'A',1),(2,'B',1),(3,'A',0),(4,'A',1)");
    $db->exec("INSERT INTO startendday VALUES
        (10,1,7,'2026-11-01','23:00:00',1,'2026-11-02','03:00:00'),
        (20,2,8,'2026-11-01','08:00:00',0,NULL,NULL),
        (30,3,7,'2026-11-01','08:00:00',0,NULL,NULL),
        (40,1,7,'2026-11-02','04:00:00',0,NULL,NULL)");
    $db->exec("INSERT INTO salesman VALUES (7,'S7','Test salesman')");
    $db->exec("INSERT INTO customermaster VALUES (1,'C1','Test customer',1),(2,'','Unplanned',1),(3,'C3','Inactive',0),(4,'C4','Unvisited',1)");
    $db->exec('INSERT INTO routesequencecustomerstatus VALUES (10,1,1,10),(10,1,1,15),(10,4,1,20),(20,1,1,-5)');
    $db->exec("INSERT INTO customervisitlog (routekey, logkey, customercode, onpremisevisit, logstartdate, logstarttime, logenddate, logendtime) VALUES
        (10,1,1,1,'2026-11-01','23:55:00','2026-11-02','00:15:00'),
        (10,2,1,1,'2026-11-02','00:30:00','2026-11-02','00:35:00'),
        (10,3,1,0,'2026-11-02','01:00:00','2026-11-02','01:20:00'),
        (10,4,1,1,'2026-11-02','01:30:00',NULL,NULL),
        (10,5,1,1,'2026-11-02','02:00:00','2026-11-02','01:59:00'),
        (10,6,2,1,'2026-11-02','02:10:00','2026-11-02','02:20:00'),
        (10,7,3,1,'2026-11-02','02:30:00','2026-11-02','02:40:00'),
        (10,8,999,1,'2026-11-02','02:40:00','2026-11-02','02:50:00'),
        (20,9,1,1,'2026-11-01','08:00:00','2026-11-01','08:01:30'),
        (30,10,1,1,'2026-11-01','08:00:00','2026-11-01','08:10:00'),
        (40,11,1,1,'2026-11-02','04:00:00','2026-11-02','04:10:00')");
});

test('customer visit report retains individual visits with exact columns and Yes No qualifications', function () {
    $rows = customerVisitReportRows($this->customerVisitDb, ' A ');
    expect($rows)->toHaveCount(6)
        ->and(array_keys($rows[0]))->toBe(['Division', 'Route Code', 'Salesman Name', 'Cust. Code', 'Cust. Name', 'Planned Call', 'Actual Visit', 'CFT Achieved', 'Non-Premise Vist (OTP)'])
        ->and(array_column($rows, 'Actual Visit'))->toBe(array_fill(0, 6, 'Yes'))
        ->and(array_column($rows, 'CFT Achieved'))->toBe(['Yes', 'No', 'No', 'No', 'No', 'Yes'])
        ->and(array_column($rows, 'Non-Premise Vist (OTP)'))->toBe(['No', 'No', 'Yes', 'No', 'No', 'No'])
        ->and(array_column($rows, 'Planned Call'))->toBe(['Yes', 'Yes', 'Yes', 'Yes', 'Yes', 'No'])
        ->and($rows[0])->toMatchArray(['Division' => 'A', 'Salesman Name' => 'Test salesman', 'Cust. Code' => 'C1'])
        ->and($rows[5]['Cust. Code'])->toBe('2');
});

test('customer visit report uses each visit target and does not sum short revisits', function () {
    $db = $this->customerVisitDb;
    $db->exec('UPDATE customervisitlog SET cft=21 WHERE customercode=1');
    expect(array_column(customerVisitReportRows($db, 'A'), 'CFT Achieved'))->toBe(['No', 'No', 'No', 'No', 'No', 'Yes']);
    $db->exec('UPDATE customervisitlog SET cft=20 WHERE logkey=1');
    expect(customerVisitReportRows($db, 'A')[0]['CFT Achieved'])->toBe('Yes');
    $db->exec('UPDATE customervisitlog SET cft=0 WHERE logkey=1');
    expect(customerVisitReportRows($db, 'A')[0]['CFT Achieved'])->toBe('No');
});

test('customer visit report uses premises flag for indicator independently of matched OTP', function () {
    $db = $this->customerVisitDb;
    $db->exec("INSERT INTO otplogdetail VALUES (1,1,1,'2026-11-02','00:00:00'),(2,1,1,'2026-11-02','00:01:00')");
    $rows = customerVisitReportRows($db, 'A');
    expect($rows)->toHaveCount(6)
        ->and($rows[0])->toMatchArray(['CFT Achieved' => 'No', 'Non-Premise Vist (OTP)' => 'No'])
        ->and($rows[2])->toMatchArray(['CFT Achieved' => 'No', 'Non-Premise Vist (OTP)' => 'Yes']);
    $db->exec('UPDATE customervisitlog SET onpremisevisit=NULL WHERE logkey=1');
    $db->exec('UPDATE customervisitlog SET onpremisevisit=2 WHERE logkey=2');
    expect(array_slice(array_column(customerVisitReportRows($db, 'A'), 'Non-Premise Vist (OTP)'), 0, 2))->toBe(['No', 'No']);
    $db->exec('DELETE FROM otplogdetail');
    $db->exec('UPDATE customervisitlog SET onpremisevisit=1 WHERE logkey=1');
    $db->exec("INSERT INTO otplogdetail VALUES (3,1,1,'2026-11-02','03:30:00')");
    expect(customerVisitReportRows($db, 'A')[0]['CFT Achieved'])->toBe('Yes');
    $db->exec('UPDATE startendday SET routeclosed=0 WHERE routekey=10');
    $db->exec("UPDATE otplogdetail SET otptime='04:01:00'");
    expect(customerVisitReportRows($db, 'A')[0]['CFT Achieved'])->toBe('Yes');
});

test('customer visit report filters active records division and journey start dates', function () {
    $rows = customerVisitReportRows($this->customerVisitDb, ' all ');
    expect($rows)->toHaveCount(7)
        ->and($rows[0]['Salesman Name'])->toBe('0')
        ->and(customerVisitReportRows($this->customerVisitDb, 'missing'))->toBe([])
        ->and(customerVisitReportRows($this->customerVisitDb, 'ALL', '2026-11-03', '2026-11-03'))->toBe([])
        ->and(customerVisitReportRows($this->customerVisitDb, 'A', '2026-11-02', '2026-11-02'))->toHaveCount(1);
});
