<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvExporter
{
    /**
     * @param list<string> $headers
     * @param iterable<mixed> $rows
     * @param callable(mixed): list<scalar|null> $mapRow
     */
    public function createResponse(string $filename, array $headers, iterable $rows, callable $mapRow): StreamedResponse
    {
        $response = new StreamedResponse(static function () use ($headers, $rows, $mapRow): void {
            // Excel recognizes this marker and opens the file as UTF-8.
            echo "\xEF\xBB\xBF";
            self::writeRow($headers);

            foreach ($rows as $row) {
                $values = $mapRow($row);
                $values = array_map(self::protectSpreadsheetFormula(...), $values);
                self::writeRow($values);
            }
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename)
        );
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private static function protectSpreadsheetFormula(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^\s*[=+\-@]/u', $value) === 1) {
            return "'" . $value;
        }

        return $value;
    }

    /** @param list<scalar|null> $values */
    private static function writeRow(array $values): void
    {
        $row = fopen('php://temp', 'w+b');
        if ($row === false) {
            return;
        }

        fputcsv($row, $values, ',', '"', '', "\r\n");
        rewind($row);
        echo stream_get_contents($row);
        fclose($row);
    }
}
