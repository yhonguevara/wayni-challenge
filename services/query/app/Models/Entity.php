<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entity extends Model
{
    protected $table = 'entities';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'entity_code',
        'total_loan_amount',
    ];

    protected function casts(): array
    {
        return [
            'total_loan_amount' => 'decimal:2',
        ];
    }
}
