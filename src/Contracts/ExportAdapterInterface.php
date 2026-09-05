<?php

declare(strict_types=1);

namespace Modufolio\Panel\Contracts;

/**
 * One download format for a listing: CSV, a spreadsheet, JSON.
 *
 * The rows arrive exactly as the resource presents them for the table, and
 * the columns as `key`/`label` pairs in the order the file should show them.
 */
interface ExportAdapterInterface
{
    /** The format key a request names: `csv`, `xlsx`, `json`. */
    public function getFormat(): string;

    public function getMimeType(): string;

    public function getFileExtension(): string;

    /**
     * @param list<array<string, mixed>>               $data    the presented rows
     * @param list<array{key: string, label: string}>  $columns in file order
     */
    public function export(array $data, array $columns): string;
}
