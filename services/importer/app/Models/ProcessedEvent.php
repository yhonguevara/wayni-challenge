<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedEvent extends Model
{
    protected $table = 'processed_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'import_id',
        'event_id',
    ];

    /**
     * Only created_at is tracked — no updated_at column on this table.
     */
    public $timestamps = false;

    /**
     * Enable created_at management only.
     */
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;
}
