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
        Schema::connection($this->getConnection())->create('lookout_exception_groups', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->string('exception_class', 500);
            $table->string('file', 500);
            $table->integer('line');
            $table->text('message');
            $table->timestamp('first_seen');
            $table->timestamp('last_seen')->index();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->string('status', 20)->default('unresolved')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('lookout_exception_groups');
    }
};
