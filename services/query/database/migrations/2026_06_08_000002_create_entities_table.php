<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->string('entity_code', 5)->unique();
            $table->decimal('total_loan_amount', 18, 2)->default(0);
            $table->timestampsTz();

            $table->index('total_loan_amount', 'idx_entities_loan_amount');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};
