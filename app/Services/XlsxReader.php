<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal .xlsx reader: the first worksheet as rows of text. Date cells come back as Y-m-d.
 */
class XlsxReader
{
    private const BUILT_IN_DATE_FORMATS = [14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 45, 46, 47, 50, 51, 52, 53, 54, 55, 56, 57, 58];

    /**
     * @return array<int, array<int, string>> rows keyed by their 1-based spreadsheet row number
     */
    public function read(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'Dit is geen geldig Excel-bestand (.xlsx).']);
        }

        try {
            $sheetPath = $this->firstSheetPath($zip);
            $sharedStrings = $this->sharedStrings($zip);
            $dateStyles = $this->dateStyles($zip);
            $sheet = $this->xml((string) $zip->getFromName($sheetPath));
        } finally {
            $zip->close();
        }

        $rows = [];

        foreach ($sheet->sheetData->row ?? [] as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $cells[$this->columnIndex((string) $cell['r'])] = $this->cellValue($cell, $sharedStrings, $dateStyles);
            }

            if ($cells === []) {
                continue;
            }

            $values = array_fill(0, max(array_keys($cells)) + 1, '');

            foreach ($cells as $index => $value) {
                $values[$index] = $value;
            }

            $rows[(int) $row['r']] = $values;
        }

        return $rows;
    }

    private function xml(string $contents): SimpleXMLElement
    {
        $contents = preg_replace('/ xmlns="[^"]*"/', '', $contents);
        $xml = $contents === '' ? false : simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET);

        if ($xml === false) {
            throw ValidationException::withMessages(['file' => 'Het Excel-bestand kon niet worden gelezen.']);
        }

        return $xml;
    }

    private function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $this->xml((string) $zip->getFromName('xl/workbook.xml'));
        $relations = $this->xml((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        $first = $workbook->sheets->sheet[0] ?? null;
        $relationId = $first !== null ? (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';

        foreach ($relations->Relationship as $relation) {
            if ($relationId !== '' && (string) $relation['Id'] === $relationId) {
                return 'xl/'.ltrim((string) $relation['Target'], '/');
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $contents = $zip->getFromName('xl/sharedStrings.xml');

        if ($contents === false) {
            return [];
        }

        $strings = [];

        foreach ($this->xml($contents)->si as $item) {
            $strings[] = $this->text($item);
        }

        return $strings;
    }

    private function text(SimpleXMLElement $element): string
    {
        $text = '';

        foreach ($element->xpath('.//t') ?: [] as $part) {
            $text .= (string) $part;
        }

        return $text;
    }

    /**
     * Style indexes whose number format is a date.
     *
     * @return array<int, bool>
     */
    private function dateStyles(ZipArchive $zip): array
    {
        $contents = $zip->getFromName('xl/styles.xml');

        if ($contents === false) {
            return [];
        }

        $styles = $this->xml($contents);
        $customDates = [];

        foreach ($styles->numFmts->numFmt ?? [] as $format) {
            if (preg_match('/[dmy]/i', preg_replace('/"[^"]*"|\[[^\]]*\]/', '', (string) $format['formatCode']))) {
                $customDates[(int) $format['numFmtId']] = true;
            }
        }

        $dates = [];

        $index = 0;

        foreach ($styles->cellXfs->xf ?? [] as $xf) {
            $id = (int) $xf['numFmtId'];
            $dates[$index++] = in_array($id, self::BUILT_IN_DATE_FORMATS, true) || isset($customDates[$id]);
        }

        return $dates;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @param  array<int, bool>  $dateStyles
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings, array $dateStyles): string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            return trim($this->text($cell->is));
        }

        $value = (string) $cell->v;

        if ($type === 's') {
            return trim($sharedStrings[(int) $value] ?? '');
        }

        if ($type === 'b') {
            return $value === '1' ? 'Ja' : 'Nee';
        }

        if ($type === '' || $type === 'n') {
            if ($value !== '' && is_numeric($value) && ($dateStyles[(int) $cell['s']] ?? false)) {
                return gmdate('Y-m-d', (int) round(((float) $value - 25569) * 86400));
            }

            return $value !== '' && is_numeric($value) && str_contains($value, '.') ? rtrim(rtrim(number_format((float) $value, 10, '.', ''), '0'), '.') : trim($value);
        }

        return trim($value);
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/', $reference, $match);
        $index = 0;

        foreach (str_split($match[0] ?? 'A') as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
