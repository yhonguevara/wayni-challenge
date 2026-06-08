<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ImportLog extends Model
{
    use HasUuids;

    protected $table = 'import_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'filename',
        'status',
        'total_lines',
        'total_debtors',
        'total_entities',
        'duration_ms',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'total_lines' => 'integer',
            'total_debtors' => 'integer',
            'total_entities' => 'integer',
            'duration_ms' => 'integer',
        ];
    }
}
