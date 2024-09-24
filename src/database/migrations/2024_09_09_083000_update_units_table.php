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
        Schema::table('units', function ($table) {
            $table->unsignedBigInteger('unit_id');
            $table->unique('unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function ($table) {
            $table->dropUnique('unit_id');
            $table->dropColumn('unit_id');
        });
    }
};
