<?php

namespace App\Domain\Spreadsheet\Enums;

enum CellValueType: string
{
    case String = 'string';
    case Number = 'number';
    case Boolean = 'boolean';
    case Formula = 'formula';
    case Error = 'error';
    case Empty = 'empty';
}
