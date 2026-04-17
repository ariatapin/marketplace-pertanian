<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('recommendation_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('rule_key', 100);
            $table->string('dispatch_key', 160)->unique();
            $table->jsonb('context')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'role']);
            $table->index(['rule_key', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_dispatches');
    }
};

