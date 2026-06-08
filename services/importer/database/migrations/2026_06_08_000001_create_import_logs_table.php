<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('filename', 255);
            $table->string('status', 20)->default('pending');
            $table->integer('total_records')->nullable();
            $table->integer('valid_records')->nullable();
            $table->integer('invalid_records')->nullable();
            $table->integer('duration')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
