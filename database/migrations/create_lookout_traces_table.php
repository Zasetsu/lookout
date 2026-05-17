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
        Schema::connection($this->getConnection())->create('lookout_traces', function (Blueprint $table) {
            $table->id();
            $table->uuid('trace_id')->unique();
            $table->string('type', 50)->index();
            $table->string('name', 500);
            $table->string('status', 20)->index();
            $table->timestamp('timestamp')->index();
            $table->unsignedInteger('duration')->nullable();
            $table->unsignedInteger('memory_peak')->nullable();
            $table->string('user_id', 255)->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->string('method', 10)->nullable();
            $table->text('url')->nullable();
            $table->text('request_headers')->nullable();
            $table->text('request_body')->nullable();
            $table->integer('response_status')->nullable();
            $table->text('response_headers')->nullable();
            $table->text('tags')->nullable();
            $table->string('environment', 50)->nullable();
            $table->index('duration');
            $table->index('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('lookout_traces');
    }
};
