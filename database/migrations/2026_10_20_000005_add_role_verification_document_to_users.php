<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role_document_path')->nullable();
            $table->string('role_document_name')->nullable();
            $table->string('role_document_mime', 100)->nullable();
            $table->timestamp('role_document_uploaded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role_document_path', 'role_document_name', 'role_document_mime', 'role_document_uploaded_at']);
        });
    }
};
