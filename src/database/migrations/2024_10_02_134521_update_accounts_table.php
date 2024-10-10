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
        Schema::table('accounts', function ($table) {
            $table->tinyInteger('blocked')->nullable()->default(0);
            $table->string('comment')->nullable()->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function ($table) {
            $table->dropColumn('blocked');
            $table->dropColumn('comment');
        });
    }
};
