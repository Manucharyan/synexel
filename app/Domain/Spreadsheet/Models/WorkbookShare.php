<?php

namespace App\Domain\Spreadsheet\Models;

use App\Domain\Spreadsheet\Enums\SharePermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkbookShare extends Model
{
    use HasUuids;

    protected $fillable = [
        'workbook_id',
        'user_id',
        'shared_by',
        'permission',
    ];

    protected function casts(): array
    {
        return [
            'permission' => SharePermission::class,
        ];
    }

    public function workbook(): BelongsTo
    {
        return $this->belongsTo(Workbook::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }
}
