<?php

namespace App\Domain\Spreadsheet\Models;

use App\Domain\Spreadsheet\Enums\CellValueType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cell extends Model
{
    protected $fillable = [
        'sheet_id',
        'row',
        'col',
        'raw_value',
        'formula',
        'computed_value',
        'style',
        'value_type',
    ];

    protected function casts(): array
    {
        return [
            'style' => 'array',
            'value_type' => CellValueType::class,
        ];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(Sheet::class);
    }

    public function toSnapshot(): array
    {
        return [
            'row' => $this->row,
            'col' => $this->col,
            'raw_value' => $this->raw_value,
            'formula' => $this->formula,
            'computed_value' => $this->computed_value,
            'style' => $this->style,
            'value_type' => $this->value_type?->value,
        ];
    }

    public function displayValue(): ?string
    {
        if ($this->formula) {
            return $this->computed_value;
        }

        return $this->raw_value;
    }
}
