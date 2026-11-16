<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait HandlesCsv
{
    /**
     * Stream a semicolon-delimited, BOM-prefixed CSV that opens cleanly in Excel.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    protected function streamCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';', '"', '\\');

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($value) => $this->neutraliseFormula($value), $row), ';', '"', '\\');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Prefix cells that spreadsheet software would execute as formulas.
     */
    private function neutraliseFormula(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) && ! is_numeric($value)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Read an uploaded CSV into associative rows keyed by lower-cased header.
     * Returns rows keyed by their 1-based spreadsheet line number.
     *
     * @return array<int, array<string, string>>
     */
    protected function readCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $firstLine = (string) fgets($handle);
        rewind($handle);

        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $headers = null;
        $rows = [];
        $line = 0;

        while (($record = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $line++;

            if ($headers === null) {
                $record[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $record[0]);
                $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $record);

                continue;
            }

            if (count(array_filter($record, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $record = array_pad(array_slice($record, 0, count($headers)), count($headers), '');
            $rows[$line] = array_map(fn ($value) => trim((string) $value), array_combine($headers, $record));
        }

        fclose($handle);

        return $rows;
    }

    protected function nullIfBlank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function parseBoolean(?string $value, bool $default = true): bool
    {
        if ($value === null || trim($value) === '') {
            return $default;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'ja', 'y', 'active', 'actief'], true);
    }
}
