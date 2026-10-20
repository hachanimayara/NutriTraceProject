<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TraceabilityAlert extends Model
{
    protected $fillable = ['created_by', 'subject_type', 'subject_id', 'code', 'severity', 'title', 'explanation', 'evidence', 'status', 'resolved_at'];

    protected function casts(): array { return ['evidence' => 'array', 'resolved_at' => 'datetime']; }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function subject(): MorphTo { return $this->morphTo(); }
}
