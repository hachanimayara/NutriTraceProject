<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harvest_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 80)->unique();
            $table->date('starts_at')->index();
            $table->date('ends_at')->index();
            $table->decimal('estimated_quantity', 14, 3);
            $table->string('unit', 24)->default('kg');
            $table->string('status', 24)->default('planned')->index();
            $table->unsignedTinyInteger('reminder_days')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status', 'ends_at'], 'harvest_plans_owner_status_end_idx');
            $table->index(['farm_id', 'product_id'], 'harvest_plans_farm_product_idx');
        });

        Schema::create('harvest_plan_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('harvest_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40);
            $table->json('changes')->nullable();
            $table->timestamps();
            $table->index(['harvest_plan_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvest_plan_histories');
        Schema::dropIfExists('harvest_plans');
    }
};
