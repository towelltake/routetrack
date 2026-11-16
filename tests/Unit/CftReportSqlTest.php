<?php

// Execute the report SELECT against synthetic data with SQLite equivalents for
// MySQL scalar functions. This does not validate MySQL procedure installation.
function cftReportRows(PDO $db, string $division = 'ALL', string $from = '2026-11-01', string $to = '2026-11-01'): array
{
    $sql = file_get_contents(__DIR__.'/../../sp_cft_report.sql');
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
    $this->cftDb = new PDO('sqlite::memory:');
    $db = $this->cftDb;
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
    $db->exec("INSERT INTO routemaster VALUES (1,'A',1),(2,'B',1),(3,'A',0),(4,'A',1)");
    $db->exec("INSERT INTO startendday VALUES
        (10,1,7,'2026-11-01','23:00:00',1,'2026-11-02','03:00:00'),
        (20,2,8,'2026-11-01','08:00:00',0,NULL,NULL),
        (30,3,7,'2026-11-01','08:00:00',0,NULL,NULL),
        (40,1,7,'2026-11-02','04:00:00',0,NULL,NULL)");
    $db->exec("INSERT INTO salesman VALUES (7,'S7','Test salesman')");
    $db->exec("INSERT INTO customermaster VALUES (1,'C1','Test customer',1),(2,'','Unplanned',1),(3,'C3','Inactive',0),(4,'C4','Unvisited',1)");
    $db->exec('INSERT INTO routesequencecustomerstatus VALUES (10,1,1,10),(10,1,1,15),(10,4,1,20),(20,1,1,-5)');
    $db->exec("INSERT INTO customervisitlog VALUES
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

test('CFT report keeps visit detail and excludes ineligible time without multiplying plans', function () {
    $rows = cftReportRows($this->cftDb, ' A ');
    expect($rows)->toHaveCount(5);
    expect(array_keys($rows[0]))->toBe(['ROUTECODE', 'SALESMANCODE', 'SALESMAN', 'CUST CODE', 'CUSTOMER NAME', 'START DATE', 'START TIME', 'END DATE', 'END TIME', 'DURATION', 'Planned CFT', 'Achived CFT IN MM']);
    expect($rows[0]['SALESMANCODE'])->toBe('S7')
        ->and($rows[0]['CUST CODE'])->toBe('C1')
        ->and($rows[0]['START DATE'])->toBe('01/11/2026')
        ->and($rows[0]['END DATE'])->toBe('02/11/2026')
        ->and($rows[0]['DURATION'])->toBe('0:20:00')
        ->and($rows[0]['Planned CFT'])->toEqual(15)
        ->and(array_column($rows, 'Achived CFT IN MM'))->toEqual([20, 5, 0, 0, 0])
        ->and($rows[2]['END DATE'])->toBe('0')
        ->and($rows[2]['DURATION'])->toBe('0')
        ->and($rows[3]['DURATION'])->toBe('0')
        ->and($rows[4]['CUST CODE'])->toBe('2')
        ->and($rows[4]['Planned CFT'])->toEqual(0);
});

test('CFT report matches nearest OTP visit once and ignores events beyond journey closure', function () {
    $this->cftDb->exec("INSERT INTO otplogdetail VALUES
        (1,1,1,'2026-11-02','00:31:00'),
        (2,1,1,'2026-11-02','00:32:00'),
        (3,1,1,'2026-11-02','03:30:00')");
    $rows = cftReportRows($this->cftDb, 'A');
    expect($rows)->toHaveCount(5)
        ->and(array_column($rows, 'Achived CFT IN MM'))->toEqual([20, 0, 0, 0, 0]);
    // An open journey is instead bounded by its next start, even outside range.
    $this->cftDb->exec("UPDATE startendday SET routeclosed=0 WHERE routekey=10");
    $this->cftDb->exec("DELETE FROM otplogdetail");
    $this->cftDb->exec("INSERT INTO otplogdetail VALUES (4,1,1,'2026-11-02','04:01:00')");
    expect(cftReportRows($this->cftDb, 'A')[0]['Achived CFT IN MM'])->toEqual(20);
});

test('CFT report scopes by division and journey start including ALL and empty results', function () {
    $rows = cftReportRows($this->cftDb, ' all ');
    expect($rows)->toHaveCount(6)
        ->and($rows[0]['SALESMANCODE'])->toBe('8')
        ->and($rows[0]['SALESMAN'])->toBe('0')
        ->and($rows[0]['Planned CFT'])->toEqual(0)
        ->and($rows[0]['Achived CFT IN MM'])->toEqual(2)
        ->and(cftReportRows($this->cftDb, 'missing'))->toBe([])
        ->and(cftReportRows($this->cftDb, 'ALL', '2026-11-03', '2026-11-03'))->toBe([])
        ->and(cftReportRows($this->cftDb, 'A', '2026-11-02', '2026-11-02'))->toHaveCount(1);
});

test('CFT report excludes null and other premises flags without reassigning their OTP events', function () {
    $this->cftDb->exec("INSERT INTO customervisitlog VALUES
        (10,12,1,NULL,'2026-11-02','02:21:00','2026-11-02','02:22:00'),
        (10,13,1,2,'2026-11-02','02:23:00','2026-11-02','02:24:00')");
    $this->cftDb->exec("INSERT INTO otplogdetail VALUES (5,1,1,'2026-11-02','01:01:00')");
    $rows = cftReportRows($this->cftDb, 'A');
    expect($rows)->toHaveCount(5)
        ->and(array_column($rows, 'Achived CFT IN MM'))->toEqual([20, 5, 0, 0, 0]);
});
