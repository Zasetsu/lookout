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
        Schema::connection($this->getConnection())->create('lookout_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('metric', 50);
            $table->string('condition', 20);
            $table->decimal('value', 10, 2);
            $table->unsignedInteger('window_minutes');
            $table->text('channels');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('lookout_thresholds');
    }
};
