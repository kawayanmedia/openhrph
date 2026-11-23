<?php

namespace App\Imports\Workbook;

enum ColumnType: string
{
    case Text = 'text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Date = 'date';
    case Time = 'time';
    case Boolean = 'boolean';
}
