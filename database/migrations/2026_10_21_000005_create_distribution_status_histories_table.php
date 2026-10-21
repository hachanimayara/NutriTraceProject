<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('distribution_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['distribution_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_status_histories');
    }
};
