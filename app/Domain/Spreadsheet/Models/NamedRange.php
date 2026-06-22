<?php

namespace App\Domain\Spreadsheet\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NamedRange extends Model
{
    use HasUuids;

    protected $fillable = ['workbook_id', 'name', 'sheet_name', 'range_a1'];

    public function workbook(): BelongsTo
    {
        return $this->belongsTo(Workbook::class);
    }
}
