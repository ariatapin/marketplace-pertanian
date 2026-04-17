<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mitra_banner_entry_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('granted');
            $table->string('reason', 60)->nullable();
            $table->string('entry_source', 40)->default('banner');
            $table->foreignId('announcement_id')->nullable()->constrained('marketplace_announcements')->nullOnDelete();
            $table->timestamp('signed_expires_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('session_id', 120)->nullable();
            $table->string('request_url', 2000)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('status');
            $table->index('entry_source');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mitra_banner_entry_audits');
    }
};

