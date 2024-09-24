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
            $table->integer('retention')->nullable()->default(0);
            $table->string('country')->nullable()->default('any');
            $table->string('fill_type')->nullable()->default('fast');
            $table->string('open_time')->nullable()->default('5 - 10');
            $table->tinyInteger('share')->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
