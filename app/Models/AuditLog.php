<?php

namespace App\Models;

use App\Domain\Spreadsheet\Enums\AuditAction;
use App\Domain\Spreadsheet\Models\Workbook;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'summary',
        'workbook_id',
        'workbook_name',
        'sheet_id',
        'sheet_name',
        'target',
        'operation_id',
        'details',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workbook(): BelongsTo
    {
        return $this->belongsTo(Workbook::class);
    }
}
