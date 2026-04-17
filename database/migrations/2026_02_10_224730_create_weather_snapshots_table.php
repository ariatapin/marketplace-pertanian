<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('weather_snapshots', function (Blueprint $table) {
            $table->id();

            // sumber data
            $table->string('provider')->default('openweather');

            // lokasi (flexible: city / warehouse / farm)
            $table->string('location_type');        // city | warehouse | farm
            $table->unsignedBigInteger('location_id');

            // koordinat
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);

            // data cuaca mentah
            $table->jsonb('payload');

            // cache control
            $table->timestampTz('fetched_at');
            $table->timestampTz('valid_until');

            $table->timestampsTz();

            $table->index(['location_type', 'location_id']);
            $table->index(['valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_snapshots');
    }
};
