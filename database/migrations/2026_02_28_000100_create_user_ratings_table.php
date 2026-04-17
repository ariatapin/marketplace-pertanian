<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rated_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->text('review')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'buyer_id', 'rated_user_id'], 'uq_user_ratings_order_buyer_rated');
            $table->index('rated_user_id');
            $table->index('buyer_id');
        });

        DB::statement("ALTER TABLE user_ratings
            ADD CONSTRAINT chk_user_ratings_score
            CHECK (score BETWEEN 1 AND 5)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE user_ratings DROP CONSTRAINT IF EXISTS chk_user_ratings_score");
        Schema::dropIfExists('user_ratings');
    }
};
