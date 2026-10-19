<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique();
            $table->string('role', 24)->default('consommateur')->index();
            $table->string('status', 20)->default('approved')->index();
            $table->timestamp('approved_at')->nullable();
            // Kept as an indexed scalar to let SQLite add columns without rebuilding
            // the existing users table or risking existing application data.
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('google_id')->nullable()->unique();
            $table->boolean('is_protected_admin')->default(false);
            $table->unsignedInteger('session_version')->default(0);
        });

        Schema::table('users', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropUnique(['google_id']);
            $table->dropColumn(['phone', 'role', 'status', 'approved_at', 'approved_by', 'google_id', 'is_protected_admin', 'session_version', 'deleted_at']);
        });
    }
};
