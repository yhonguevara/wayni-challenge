<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debtors', function (Blueprint $table) {
            $table->id();
            $table->string('identification_number', 11)->unique();
            $table->string('max_situation', 2);
            $table->decimal('total_loan_amount', 18, 2)->default(0);
            $table->timestampsTz();

            $table->index('max_situation', 'idx_debtors_situation');
            $table->index('total_loan_amount', 'idx_debtors_loan_amount');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debtors');
    }
};
