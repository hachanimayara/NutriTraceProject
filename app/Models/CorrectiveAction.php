<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorrectiveAction extends Model
{
    protected $fillable = ['alert_id', 'responsible_id', 'created_by', 'description', 'due_at', 'status', 'evidence_path', 'completed_at'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(TraceabilityAlert::class, 'alert_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ComplianceEvent::class, 'corrective_action_id')->latest();
    }
}
