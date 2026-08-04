<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Response;
use RuntimeException;

class ExcelXmlWorkbook
{
    private const SPREADSHEET_NS = 'urn:schemas-microsoft-com:office:spreadsheet';

    public static function download(string $filename, array $headers, array $rows = [], string $worksheetName = 'Sheet1'): Response
    {
        return response(
            self::buildDocument($headers, $rows, $worksheetName),
            200,
            [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            ]
        );
    }

    public static function buildDocument(array $headers, array $rows = [], string $worksheetName = 'Sheet1'): string
    {
        $headerCells = implode('', array_map(
            fn ($header) => self::cell((string) $header, 'Header'),
            $headers
        ));

        $bodyRows = implode('', array_map(function (array $row) use ($headers) {
            $cells = '';

            foreach ($headers as $header) {
                $value = $row[$header] ?? '';
                $cells .= self::cell($value);
            }

            return '<Row>' . $cells . '</Row>';
        }, $rows));

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Bottom"/>
   <Borders/>
   <Font ss:FontName="Calibri" ss:Size="11"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID="Header">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1"/>
   <Interior ss:Color="#D9E2F3" ss:Pattern="Solid"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="{$worksheetName}">
  <Table>
   <Row>{$headerCells}</Row>
   {$bodyRows}
  </Table>
 </Worksheet>
</Workbook>
XML;
    }

    public static function parseFile(string $path): array
    {
        $content = @file_get_contents($path);

        if ($content === false || trim($content) === '') {
            throw new RuntimeException('The uploaded Excel file is empty.');
        }

        $document = new DOMDocument();
        $loaded = @$document->loadXML($content);

        if (! $loaded) {
            throw new RuntimeException('Invalid Excel XML format. Use the downloaded bulk import template.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ss', self::SPREADSHEET_NS);

        $rowNodes = $xpath->query('//ss:Worksheet[1]/ss:Table/ss:Row');

        if (! $rowNodes || $rowNodes->length === 0) {
            throw new RuntimeException('The uploaded Excel file does not contain any worksheet rows.');
        }

        $rows = [];
        $headers = null;

        foreach ($rowNodes as $rowNode) {
            $values = self::extractRowValues($xpath, $rowNode);

            if ($headers === null) {
                $headers = array_map(
                    fn ($value) => trim((string) $value),
                    $values
                );
                continue;
            }

            $record = [];
            $hasData = false;

            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $value = trim((string) ($values[$index] ?? ''));
                $record[$header] = $value;
                $hasData = $hasData || $value !== '';
            }

            if ($hasData) {
                $rows[] = $record;
            }
        }

        if ($headers === null || count(array_filter($headers, fn ($header) => $header !== '')) === 0) {
            throw new RuntimeException('The uploaded Excel file is missing the header row.');
        }

        return $rows;
    }

    private static function extractRowValues(DOMXPath $xpath, \DOMNode $rowNode): array
    {
        $values = [];
        $columnIndex = 1;
        $cells = $xpath->query('ss:Cell', $rowNode);

        foreach ($cells as $cell) {
            $explicitIndex = $cell->attributes?->getNamedItemNS(self::SPREADSHEET_NS, 'Index');

            if ($explicitIndex !== null) {
                $columnIndex = (int) $explicitIndex->nodeValue;
            }

            $dataNode = $xpath->query('ss:Data', $cell)->item(0);
            $values[$columnIndex - 1] = $dataNode?->textContent ?? '';
            $columnIndex++;
        }

        if ($values === []) {
            return [];
        }

        ksort($values);
        $lastIndex = max(array_keys($values));
        $normalized = [];

        for ($index = 0; $index <= $lastIndex; $index++) {
            $normalized[$index] = $values[$index] ?? '';
        }

        return $normalized;
    }

    private static function cell(mixed $value, ?string $styleId = null): string
    {
        $value = htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $style = $styleId ? ' ss:StyleID="' . $styleId . '"' : '';

        return '<Cell' . $style . '><Data ss:Type="String">' . $value . '</Data></Cell>';
    }
}
