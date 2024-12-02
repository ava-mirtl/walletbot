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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('token_id')->constrained('contracts')->onDelete('cascade');
            $table->enum('type', ['buy', 'sell']);
            $table->decimal('amount', 20, 10);
            $table->decimal('price', 20, 10);
            $table->decimal('sum', 20, 5);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
