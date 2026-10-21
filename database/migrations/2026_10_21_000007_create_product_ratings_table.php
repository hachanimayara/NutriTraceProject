<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consumer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status', 16)->default('published')->index();
            $table->timestamps();
            $table->unique(['product_id', 'consumer_id'], 'product_ratings_product_consumer_unique');
            $table->index(['product_id', 'status', 'created_at'], 'product_ratings_public_product_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_ratings');
    }
};
