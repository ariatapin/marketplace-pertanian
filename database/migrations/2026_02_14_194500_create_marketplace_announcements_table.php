<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('info');
            $table->string('title', 120);
            $table->text('message');
            $table->string('cta_label', 40)->nullable();
            $table->string('cta_url')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'type']);
            $table->index('sort_order');
            $table->index('starts_at');
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_announcements');
    }
};
