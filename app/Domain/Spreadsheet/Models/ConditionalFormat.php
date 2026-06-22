<?php

namespace App\Domain\Spreadsheet\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConditionalFormat extends Model
{
    use HasUuids;

    protected $fillable = [
        'workbook_id',
        'sheet_name',
        'range_a1',
        'rule_type',
        'formula',
        'style',
        'priority',
    ];

    protected function casts(): array
    {
        return ['style' => 'array'];
    }

    public function workbook(): BelongsTo
    {
        return $this->belongsTo(Workbook::class);
    }
}
