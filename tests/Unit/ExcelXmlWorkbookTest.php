<?php

use App\Support\ExcelXmlWorkbook;

it('builds and parses an excel xml workbook', function () {
    $headers = ['route_name', 'company_code', 'status'];
    $rows = [
        [
            'route_name' => 'Route A',
            'company_code' => '10',
            'status' => '1',
        ],
        [
            'route_name' => 'Route B',
            'company_code' => '20',
            'status' => '0',
        ],
    ];

    $content = ExcelXmlWorkbook::buildDocument($headers, $rows, 'Routes');
    $path = tempnam(sys_get_temp_dir(), 'excel-xml-');

    file_put_contents($path, $content);

    expect(ExcelXmlWorkbook::parseFile($path))->toBe($rows);

    @unlink($path);
});
