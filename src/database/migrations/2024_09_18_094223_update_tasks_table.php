<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function ($table) {
            $table->integer('attempts_changes')->nullable()->default(0)->after('attempts');
            $table->integer('bundles')->nullable()->default(0)->after('attempts_changes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function ($table) {
            $table->dropColumn('attempts_changes');
            $table->dropColumn('bundles');
        });
    }
};
