<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Employee;
use App\Services\ImportNormalizer;
use App\Services\XlsxReader;
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
     * Read an uploaded CSV or Excel file into associative rows keyed by lower-cased header.
     * Returns rows keyed by their 1-based spreadsheet line number.
     * With $fields, Dutch (Simplicate) headings and values are translated to the importer's own format.
     *
     * @param  array<int, string>  $fields
     * @return array<int, array<string, string>>
     */
    protected function readCsv(UploadedFile $file, array $fields = []): array
    {
        $normalizer = new ImportNormalizer;
        $headers = null;
        $rows = [];

        foreach ($this->records($file) as $line => $record) {
            if ($headers === null) {
                $record[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $record[0]);
                $headers = array_map(fn ($header) => $fields === [] ? strtolower(trim((string) $header)) : $normalizer->field((string) $header, $fields), $record);

                continue;
            }

            if (count(array_filter($record, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $record = array_pad(array_slice($record, 0, count($headers)), count($headers), '');
            $row = [];

            foreach ($headers as $index => $header) {
                $row[$header] ??= $fields === [] ? trim((string) $record[$index]) : $normalizer->value($header, (string) $record[$index]);
            }

            $rows[$line] = $row;
        }

        return $rows;
    }

    /**
     * Raw records of a CSV (semicolon or comma separated) or .xlsx file, keyed by line number.
     *
     * @return iterable<int, array<int, string>>
     */
    private function records(UploadedFile $file): iterable
    {
        if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            yield from (new XlsxReader)->read($file->getRealPath());

            return;
        }

        $handle = fopen($file->getRealPath(), 'r');
        $firstLine = (string) fgets($handle);
        rewind($handle);

        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        $line = 0;

        while (($record = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            yield ++$line => $record;
        }

        fclose($handle);
    }

    /**
     * Employee by e-mail address or, as Simplicate exports it, by full name.
     */
    protected function findEmployee(string $emailOrName): ?Employee
    {
        $needle = mb_strtolower(trim($emailOrName));

        return Employee::where('email', $needle)->first()
            ?? Employee::all()->first(fn (Employee $employee) => mb_strtolower($employee->full_name) === $needle);
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
