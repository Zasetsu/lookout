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
        Schema::connection($this->getConnection())->table('lookout_thresholds', function (Blueprint $table) {
            $table->unsignedInteger('cooldown_minutes')->default(15)->after('window_minutes');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('lookout_thresholds', function (Blueprint $table) {
            $table->dropColumn('cooldown_minutes');
        });
    }
};
