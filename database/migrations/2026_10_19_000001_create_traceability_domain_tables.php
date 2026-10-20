<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('reference')->unique();
            $table->string('location');
            $table->string('country')->default('Tunisie');
            $table->string('farming_method')->nullable();
            $table->decimal('area_hectares', 12, 3)->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('reference')->unique();
            $table->string('category')->index();
            $table->string('origin')->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 14, 3)->nullable();
            $table->string('unit', 24)->default('kg');
            $table->string('status')->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->uuid('public_id')->unique();
            $table->decimal('quantity', 14, 3);
            $table->string('unit', 24)->default('kg');
            $table->string('status')->default('active')->index();
            $table->date('produced_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('transformations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('name');
            $table->string('facility')->nullable();
            $table->timestamp('performed_at')->index();
            $table->text('process_description')->nullable();
            $table->decimal('energy_kwh', 14, 3)->nullable();
            $table->decimal('water_liters', 14, 3)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('recorded')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('transformation_lot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transformation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lot_id')->constrained()->restrictOnDelete();
            $table->string('direction', 8);
            $table->decimal('quantity', 14, 3);
            $table->string('unit', 24)->default('kg');
            $table->timestamps();
            $table->unique(['transformation_id', 'lot_id', 'direction'], 'transformation_lot_unique');
            $table->index(['lot_id', 'direction']);
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('reference')->unique();
            $table->string('address');
            $table->string('city')->nullable();
            $table->string('country')->default('Tunisie');
            $table->string('contact_name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lot_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->string('reference')->unique();
            $table->decimal('quantity_shipped', 14, 3);
            $table->decimal('quantity_delivered', 14, 3)->nullable();
            $table->string('unit', 24)->default('kg');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('expected_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('status')->default('planned')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('scheme');
            $table->string('certificate_number')->unique();
            $table->string('issuer');
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->string('document_path')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('environmental_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stage')->index();
            $table->string('metric_code')->index();
            $table->string('name');
            $table->decimal('value', 16, 5);
            $table->string('unit', 32);
            $table->string('method')->nullable();
            $table->date('measured_at')->nullable()->index();
            $table->text('source_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('traceability_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('code')->index();
            $table->string('severity')->default('warning')->index();
            $table->string('title');
            $table->text('explanation');
            $table->json('evidence')->nullable();
            $table->string('status')->default('open')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traceability_alerts');
        Schema::dropIfExists('environmental_indicators');
        Schema::dropIfExists('certifications');
        Schema::dropIfExists('distributions');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('transformation_lot');
        Schema::dropIfExists('transformations');
        Schema::dropIfExists('lots');
        Schema::dropIfExists('products');
        Schema::dropIfExists('farms');
    }
};
