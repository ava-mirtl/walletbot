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
        Schema::table('portfolios', function (Blueprint $table) {
            $table->boolean('is_roi_shown')->default(1)->after('is_public');
            $table->boolean('is_achievements_shown')->default(1)->after('is_public');
            $table->boolean('is_activities_shown')->default(1)->after('is_public');
            $table->boolean('is_prices_shown')->default(1)->after('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropColumn('is_roi_shown');
            $table->dropColumn('is_achievements_shown');
            $table->dropColumn('is_activities_shown');
            $table->dropColumn('is_prices_shown');
        });
    }
};
