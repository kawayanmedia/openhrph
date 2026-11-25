<?php

namespace App\Sectors\Contracts;

/**
 * A printable HR document a sector contributes.
 */
interface DocumentTemplate
{
    public function key(): string;

    public function label(): string;

    /** Blade view that renders the document. */
    public function view(): string;
}
