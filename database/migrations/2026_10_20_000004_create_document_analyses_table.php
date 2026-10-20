<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_filename', 255);
            $table->string('storage_path');
            $table->string('mime_type', 100);
            $table->timestamp('external_processing_consented_at')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->json('extracted_data')->nullable();
            $table->text('summary')->nullable();
            $table->json('anomalies')->nullable();
            $table->string('ai_model', 100)->nullable();
            $table->string('failure_code', 40)->nullable();
            $table->string('admin_decision', 20)->nullable();
            $table->text('review_comment')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['uploaded_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_analyses');
    }
};
