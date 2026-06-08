<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    protected $table = 'import_logs';

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
