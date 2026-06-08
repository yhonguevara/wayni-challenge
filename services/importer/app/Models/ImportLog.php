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
        'total_records',
        'valid_records',
        'invalid_records',
        'duration',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'total_records' => 'integer',
            'valid_records' => 'integer',
            'invalid_records' => 'integer',
            'duration' => 'integer',
        ];
    }
}
