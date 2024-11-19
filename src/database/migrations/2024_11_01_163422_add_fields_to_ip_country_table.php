<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use IpCountryDetector\Models\IpCountry;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(IpCountry::TABLE, function (Blueprint $table) {
            $table->string('latitude')->nullable()->after('timezone');
            $table->string('longitude')->nullable()->after('latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(IpCountry::TABLE, function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
