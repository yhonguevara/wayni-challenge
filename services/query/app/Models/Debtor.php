<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Debtor extends Model
{
    protected $table = 'debtors';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'identification_number',
        'max_situation',
        'total_loan_amount',
    ];

    protected function casts(): array
    {
        return [
            'total_loan_amount' => 'decimal:2',
        ];
    }
}
