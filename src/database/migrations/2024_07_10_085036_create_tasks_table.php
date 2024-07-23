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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            // base
            $table->string('status')->default('created');
            $table->integer('unit_id');
            $table->string('type')->default('install');
            $table->integer('attempts')->default(3);
            $table->integer('delay_min');
            $table->integer('delay_max');

            // App install
            $table->string('search_term')->nullable();
            $table->string('app_name')->nullable();
            $table->string('app_bundle')->nullable();
            $table->string('search_field')->nullable();
            $table->string('search_scroll')->nullable();
            $table->string('install_button')->nullable();
            $table->boolean('delete')->default(false);

            // YouTube Video
            $table->string('url')->nullable();
            $table->integer('minutes')->default(0);
            $table->integer('seconds')->default(0);
            $table->boolean('like')->default(false);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
