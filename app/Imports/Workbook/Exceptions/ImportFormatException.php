<?php

namespace App\Imports\Workbook\Exceptions;

use RuntimeException;

/**
 * The file could not be read as an import workbook at all.
 */
final class ImportFormatException extends RuntimeException
{
    public static function unsupportedExtension(string $extension): self
    {
        return new self(sprintf(
            'The file type "%s" is not supported. Upload an Excel workbook (.xlsx or .xls).',
            $extension === '' ? 'unknown' : $extension,
        ));
    }

    public static function unreadable(): self
    {
        return new self('The uploaded file could not be read. Upload the workbook again.');
    }

    public static function corrupt(): self
    {
        return new self('The workbook is not a valid Excel file or it is password protected.');
    }
}
