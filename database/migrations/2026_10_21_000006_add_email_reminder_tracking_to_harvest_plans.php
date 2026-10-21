<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('harvest_plans', function (Blueprint $table): void {
            $table->date('reminder_email_for')->nullable()->after('reminder_days');
            $table->timestamp('reminder_email_claimed_at')->nullable()->after('reminder_email_for');
            $table->timestamp('reminder_email_sent_at')->nullable()->after('reminder_email_claimed_at');
        });
    }

    public function down(): void
    {
        Schema::table('harvest_plans', function (Blueprint $table): void {
            $table->dropColumn(['reminder_email_for', 'reminder_email_claimed_at', 'reminder_email_sent_at']);
        });
    }
};
