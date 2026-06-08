<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Debtor extends Model
{
    protected $table = 'debtors';

    protected function casts(): array
    {
        return [
            'total_loan_amount' => 'decimal:2',
        ];
    }
}
