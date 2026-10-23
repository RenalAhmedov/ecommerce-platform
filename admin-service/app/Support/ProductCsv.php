<?php

namespace App\Support;

use App\Models\Product;
use Generator;
use Illuminate\Validation\ValidationException;
use SplFileObject;

/**
 * The product CSV format, shared by export and import so an exported file can
 * be edited in a spreadsheet and imported back.
 */
final class ProductCsv
{
    public const COLUMNS = ['sku', 'name', 'slug', 'description', 'price', 'category_slug', 'is_active', 'stock'];

    public const REQUIRED_COLUMNS = ['sku', 'name', 'price'];

    /** Lets Excel detect UTF-8, so names like "Café" open correctly. */
    private const BOM = "\xEF\xBB\xBF";

    /** @param  resource  $output */
    public static function writeHeader($output): void
    {
        fwrite($output, self::BOM);
        fputcsv($output, self::COLUMNS, escape: '');
    }

    /** @param  resource  $output */
    public static function writeProduct($output, Product $product): void
    {
        fputcsv($output, [
            $product->sku,
            self::neutralizeFormula($product->name),
            $product->slug,
            self::neutralizeFormula($product->description),
            $product->price,
            $product->category?->slug,
            $product->is_active ? '1' : '0',
            $product->inventory?->quantity ?? 0,
        ], escape: '');
    }

    /**
     * Yields each data row keyed by column name, with its spreadsheet row
     * number (the header is row 1). Unknown columns are ignored.
     *
     * @return Generator<int, array<string, string>>
     *
     * @throws ValidationException when the header lacks a required column
     */
    public static function read(string $path): Generator
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(escape: '');

        $header = self::header($file->current() ?: []);
        $file->next();

        for ($rowNumber = 2; $file->valid(); $file->next(), $rowNumber++) {
            $cells = $file->current();

            if ($cells === [null] || $cells === false) {
                continue;
            }

            $cells = array_pad(array_slice($cells, 0, count($header)), count($header), '');

            yield $rowNumber => array_map(fn ($cell) => trim((string) $cell), array_combine($header, $cells));
        }
    }

    /** @throws ValidationException when the header lacks a required column */
    public static function countRows(string $path): int
    {
        return iterator_count(self::read($path));
    }

    /**
     * Spreadsheet apps run cells starting with = + - @ as formulas ("CSV
     * injection"); a leading apostrophe makes them plain text.
     */
    private static function neutralizeFormula(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * @param  list<?string>  $cells
     * @return list<string>
     */
    private static function header(array $cells): array
    {
        // With a BOM in front, a quoted first header ("sku") keeps its quotes, so they are trimmed too.
        if (isset($cells[0])) {
            $cells[0] = str_replace(self::BOM, '', $cells[0]);
        }
        $header = array_map(fn ($cell) => strtolower(trim((string) $cell, " \t\"")), $cells);

        $missing = array_diff(self::REQUIRED_COLUMNS, $header);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'The CSV header is missing: '.implode(', ', $missing).'. Expected columns: '.implode(', ', self::COLUMNS).'.',
            ]);
        }

        return $header;
    }
}
