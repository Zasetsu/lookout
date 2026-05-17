<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection()
    {
        return config('lookout.storage.connection', 'lookout');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('lookout_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('trace_id')->index();
            $table->string('event_type', 50)->index();
            $table->timestamp('timestamp');
            $table->unsignedInteger('duration')->nullable();
            $table->text('labels')->nullable();
            $table->text('payload');
            $table->text('tags')->nullable();
            $table->index(['trace_id', 'event_type']);
            $table->foreign('trace_id')->references('trace_id')->on('lookout_traces')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('lookout_events');
    }
};
