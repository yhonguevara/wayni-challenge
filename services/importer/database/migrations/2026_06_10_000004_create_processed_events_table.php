<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processed_events', function (Blueprint $table) {
            $table->id();
            $table->string('import_id', 36);
            $table->char('event_id', 64);
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['import_id', 'event_id'], 'uniq_processed_events_import_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_events');
    }
};
