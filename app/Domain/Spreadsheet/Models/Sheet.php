<?php

namespace App\Domain\Spreadsheet\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sheet extends Model
{
    use HasUuids;

    protected $fillable = ['workbook_id', 'name', 'index', 'layout'];

    protected function casts(): array
    {
        return ['layout' => 'array'];
    }

    public function workbook(): BelongsTo
    {
        return $this->belongsTo(Workbook::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(Cell::class);
    }

    public function hiddenRows(): array
    {
        return $this->layout['hidden_rows'] ?? [];
    }
}
