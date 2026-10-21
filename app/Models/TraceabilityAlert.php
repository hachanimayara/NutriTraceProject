<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TraceabilityAlert extends Model
{
    protected $fillable = ['created_by', 'assigned_to', 'closed_by', 'subject_type', 'subject_id', 'code', 'severity', 'title', 'explanation', 'evidence', 'status', 'resolved_at', 'due_at', 'reviewed_at', 'decision', 'closure_reason', 'priority_reason', 'last_checked_at'];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'resolved_at' => 'datetime', 'due_at' => 'datetime', 'reviewed_at' => 'datetime', 'last_checked_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function correctiveActions(): HasMany
    {
        return $this->hasMany(CorrectiveAction::class, 'alert_id')->latest();
    }

    public function events(): HasMany
    {
        return $this->hasMany(ComplianceEvent::class, 'alert_id')->with('actor')->latest();
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['resolved', 'dismissed'], true);
    }
}
