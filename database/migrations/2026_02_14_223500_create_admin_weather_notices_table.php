<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_weather_notices', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20)->default('global'); // global | province | city | district
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->string('severity', 20)->default('yellow'); // green | yellow | red | unknown
            $table->string('title', 120)->nullable();
            $table->text('message');
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'severity']);
            $table->index('scope');
            $table->index('province_id');
            $table->index('city_id');
            $table->index('district_id');
            $table->index('valid_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_weather_notices');
    }
};
