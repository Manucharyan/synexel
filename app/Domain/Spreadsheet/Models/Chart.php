<?php

namespace App\Domain\Spreadsheet\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chart extends Model
{
    use HasUuids;

    protected $fillable = ['workbook_id', 'name', 'definition'];

    protected function casts(): array
    {
        return ['definition' => 'array'];
    }

    public function workbook(): BelongsTo
    {
        return $this->belongsTo(Workbook::class);
    }
}
