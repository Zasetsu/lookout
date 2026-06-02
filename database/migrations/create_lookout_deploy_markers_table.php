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
        Schema::connection($this->getConnection())->create('lookout_deploy_markers', function (Blueprint $table) {
            $table->id();
            $table->string('version', 120);
            $table->string('environment', 80);
            $table->string('commit', 120)->nullable();
            $table->string('identity_hash', 64)->unique();
            $table->string('branch', 120)->nullable();
            $table->string('author', 120)->nullable();
            $table->string('source', 120)->nullable();
            $table->string('compare_url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('deployed_at');
            $table->timestamps();

            $table->index('deployed_at');
            $table->index('environment');
            $table->index('version');
            $table->index(['environment', 'version', 'commit']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('lookout_deploy_markers');
    }
};
