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
        Schema::connection($this->getConnection())->create('lookout_audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('action', 100);
            $table->string('user_id', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('details')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('lookout_audit_log');
    }
};
