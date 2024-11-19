<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use IpCountryDetector\Models\IpCountry;

class CreateIpAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        if (!Schema::hasTable(IpCountry::TABLE)) {
            Schema::create(IpCountry::TABLE, function (Blueprint $table) {
                $table->id();
                $table->bigInteger('first_ip');
                $table->bigInteger('last_ip');
                $table->string('country');
                $table->timestamps();
            });
        }
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists(IpCountry::TABLE);
    }
}
