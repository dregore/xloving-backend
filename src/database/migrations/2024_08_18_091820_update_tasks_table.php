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
            $table->string('skip_button')->nullable()->default('Skip');
            $table->string('like_button')->nullable()->default('Like');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function ($table) {
            $table->dropColumn('skip_button');
            $table->dropColumn('like_button');
        });
    }
};
