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
        Schema::create('pnls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('token_id')->constrained('tokens')->onDelete('cascade');
            $table->decimal('current_price', 20, 10)->nullable();
            $table->decimal('network_price', 20, 10)->nullable();
            $table->decimal('coin_price', 20, 10)->nullable();
            $table->string('day')->nullable();
            $table->string('week')->nullable();
            $table->string('month')->nullable();
            $table->string('year')->nullable();
            $table->string('all_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pnls');
    }
};
