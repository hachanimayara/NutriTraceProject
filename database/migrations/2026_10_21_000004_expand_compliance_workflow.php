<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traceability_alerts', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision')->nullable();
            $table->text('closure_reason')->nullable();
            $table->string('priority_reason')->nullable();
            $table->timestamp('last_checked_at')->nullable();
        });

        Schema::create('corrective_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('traceability_alerts')->cascadeOnDelete();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description');
            $table->timestamp('due_at')->nullable()->index();
            $table->string('status', 32)->default('todo')->index();
            $table->string('evidence_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['alert_id', 'status']);
        });

        Schema::create('compliance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->nullable()->constrained('traceability_alerts')->cascadeOnDelete();
            $table->foreignId('corrective_action_id')->nullable()->constrained('corrective_actions')->cascadeOnDelete();
            $table->foreignId('certification_id')->nullable()->constrained('certifications')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['alert_id', 'created_at']);
            $table->index(['corrective_action_id', 'created_at'], 'compliance_events_action_created_idx');
            $table->index(['certification_id', 'created_at'], 'compliance_events_cert_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_events');
        Schema::dropIfExists('corrective_actions');
        Schema::table('traceability_alerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropIndex(['due_at']);
            $table->dropColumn(['due_at', 'reviewed_at', 'decision', 'closure_reason', 'priority_reason', 'last_checked_at']);
        });
    }
};
