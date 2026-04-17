<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('weather_caches', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('cache_key', 190)->unique();
            $table->string('kode_wilayah', 60)->nullable()->index();
            $table->json('payload');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();

            $table->index(['provider', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_caches');
    }
};

