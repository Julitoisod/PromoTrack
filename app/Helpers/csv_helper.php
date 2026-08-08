<?php

declare(strict_types=1);

/**
 * CSV output shared by the report and audit-log exports.
 *
 * PhpSpreadsheet is what the design document names, but nothing here needs
 * cell formatting, formulas, or multiple sheets -- only a file that opens in
 * Excel. `fputcsv` is in PHP itself and quotes and escapes correctly, which is
 * the part that is easy to get wrong by hand. If real .xlsx formatting is ever
 * required, that is the point to add the dependency.
 */

if (! function_exists('csv_from_rows')) {
    /**
     * Renders rows as a CSV string.
     *
     * @param list<array<string, mixed>> $rows
     * @param list<string>               $columns Column keys, in output order.
     *                                            Defaults to the keys of the first row.
     * @param list<string>|null          $headings Header labels; defaults to the
     *                                            column keys, title-cased.
     */
    function csv_from_rows(array $rows, array $columns = [], ?array $headings = null): string
    {
        if ($columns === []) {
            $columns = $rows === [] ? [] : array_keys((array) $rows[0]);
        }

        $headings ??= array_map(
            static fn (string $column): string => ucwords(str_replace('_', ' ', $column)),
            $columns
        );

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        // Excel reads a file as the system codepage unless it sees a BOM.
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, $headings);

        foreach ($rows as $row) {
            $line = [];

            foreach ($columns as $column) {
                $value = $row[$column] ?? '';

                $line[] = is_scalar($value) || $value === null
                    ? (string) $value
                    : json_encode($value);
            }

            fputcsv($handle, $line);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
