<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('passkeys');

        if (Schema::hasColumn('users', 'passkey_consent_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('passkey_consent_at');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('passkeys')) {
            Schema::create('passkeys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name');
                $table->string('credential_id')->unique();
                $table->json('credential');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                $table->index('user_id');
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'passkey_consent_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('passkey_consent_at')->nullable();
            });
        }
    }
};
