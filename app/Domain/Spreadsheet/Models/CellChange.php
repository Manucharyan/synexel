<?php

namespace App\Domain\Spreadsheet\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CellChange extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'operation_id',
        'workbook_id',
        'sheet_id',
        'row',
        'col',
        'before',
        'after',
        'reverted',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'reverted' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function workbook(): BelongsTo
    {
        return $this->belongsTo(Workbook::class);
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(Sheet::class);
    }
}
