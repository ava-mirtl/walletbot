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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('network')->nullable();
            $table->string('address')->nullable()->unique();
            $table->integer('tax')->nullable();
            $table->integer('quantity')->nullable();
            $table->json('api_response')->nullable();
            $table->string('name')->nullable();
            $table->string('symbol')->nullable();
            $table->decimal('price_usd', 20, 10)->nullable();
            $table->decimal('market_cap_usd', 20, 10)->nullable();
            $table->decimal('fdv_usd', 20, 10)->nullable();
            $table->decimal('total_supply', 30, 0)->nullable();
            $table->decimal('total_reserve_in_usd', 20, 10)->nullable();
            $table->decimal('volume_usd_h24', 20, 10)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
