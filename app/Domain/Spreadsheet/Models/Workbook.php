<?php

namespace App\Domain\Spreadsheet\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workbook extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'name', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sheets(): HasMany
    {
        return $this->hasMany(Sheet::class)->orderBy('index');
    }

    public function namedRanges(): HasMany
    {
        return $this->hasMany(NamedRange::class);
    }

    public function charts(): HasMany
    {
        return $this->hasMany(Chart::class);
    }

    public function conditionalFormats(): HasMany
    {
        return $this->hasMany(ConditionalFormat::class);
    }

    public function cellChanges(): HasMany
    {
        return $this->hasMany(CellChange::class);
    }
}
