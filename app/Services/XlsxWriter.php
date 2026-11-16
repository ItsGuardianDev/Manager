<?php

namespace App\Services;

use DateTimeInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use ZipArchive;

/**
 * Minimal multi-sheet .xlsx writer (styled header row, frozen header, filters, auto column widths).
 */
class XlsxWriter
{
    /** @var array<int, array{name: string, headers: array<int, string>, rows: array<int, array<int, mixed>>}> */
    private array $sheets = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public function sheet(string $name, array $headers, iterable $rows): self
    {
        $name = trim(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $name));
        $name = mb_substr($name === '' ? 'Blad' : $name, 0, 31);

        $this->sheets[] = ['name' => $name, 'headers' => $headers, 'rows' => [...$rows]];

        return $this;
    }

    public function download(string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Kon het Excel-bestand niet aanmaken.');
        }

        $count = count($this->sheets) ?: 1;
        $this->sheets = $this->sheets ?: [['name' => 'Blad', 'headers' => [], 'rows' => []]];

        $overrides = '';
        $sheetRefs = '';
        $relations = '';
        foreach ($this->sheets as $index => $sheet) {
            $n = $index + 1;
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheetRefs .= '<sheet name="'.$this->escape($sheet['name']).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $relations .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
            $zip->addFromString('xl/worksheets/sheet'.$n.'.xml', $this->sheetXml($sheet));
        }

        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $zip->addFromString('[Content_Types].xml', $head.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$overrides.'</Types>');
        $zip->addFromString('_rels/.rels', $head.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $head.'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheetRefs.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $head.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relations.'<Relationship Id="rId'.($count + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', $head.'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF2D333B"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs></styleSheet>');
        $zip->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ], ResponseHeaderBag::DISPOSITION_ATTACHMENT)->deleteFileAfterSend();
    }

    /**
     * @param  array{headers: array<int, string>, rows: array<int, array<int, mixed>>}  $sheet
     */
    private function sheetXml(array $sheet): string
    {
        $columns = count($sheet['headers']);
        $widths = array_map(fn ($header) => mb_strlen((string) $header) + 3, $sheet['headers']);

        $xml = '';
        $rowNumber = 1;
        $xml .= $this->rowXml($rowNumber, $sheet['headers'], true);

        foreach ($sheet['rows'] as $row) {
            $rowNumber++;
            foreach (array_values($row) as $i => $value) {
                $length = $value instanceof DateTimeInterface ? 11 : mb_strlen((string) $value);
                $widths[$i] = min(60, max($widths[$i] ?? 8, $length + 2));
            }
            $xml .= $this->rowXml($rowNumber, $row, false);
        }

        $cols = '';
        foreach ($widths as $i => $width) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        $last = $this->column(max($columns, 1) - 1).max($rowNumber, 1);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$cols.'</cols><sheetData>'.$xml.'</sheetData>'
            .'<autoFilter ref="A1:'.$last.'"/></worksheet>';
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function rowXml(int $number, array $values, bool $header): string
    {
        $cells = '';

        foreach (array_values($values) as $i => $value) {
            $ref = $this->column($i).$number;

            if ($value === null || $value === '') {
                continue;
            }

            if ($header) {
                $cells .= '<c r="'.$ref.'" s="1" t="inlineStr"><is><t>'.$this->escape((string) $value).'</t></is></c>';
            } elseif ($value instanceof DateTimeInterface) {
                $serial = (strtotime($value->format('Y-m-d').' UTC') / 86400) + 25569;
                $cells .= '<c r="'.$ref.'" s="2"><v>'.$serial.'</v></c>';
            } elseif (is_int($value) || is_float($value)) {
                $cells .= '<c r="'.$ref.'"><v>'.$value.'</v></c>';
            } elseif (is_bool($value)) {
                $cells .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.($value ? 'Ja' : 'Nee').'</t></is></c>';
            } else {
                $cells .= '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.$this->escape((string) $value).'</t></is></c>';
            }
        }

        return '<row r="'.$number.'">'.$cells.'</row>';
    }

    private function column(int $index): string
    {
        $name = '';
        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $name = chr(65 + $i % 26).$name;
        }

        return $name;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
