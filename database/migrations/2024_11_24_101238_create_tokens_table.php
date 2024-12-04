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
        Schema::create('tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained('portfolios')->onDelete('cascade');
            $table->string('address')->nullable();
            $table->string('name')->nullable();
            $table->string('symbol')->nullable();
            $table->string('network')->nullable();
            $table->integer('total_amount')->nullable();
            $table->json('total_invest')->nullable();
            $table->decimal('network_price', 20, 10)->nullable();
            $table->decimal('market_cap_usd', 20, 10)->nullable();
            $table->decimal('profit', 20, 10)->nullable();
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
