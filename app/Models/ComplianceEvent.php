<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceEvent extends Model
{
    protected $fillable = ['alert_id', 'corrective_action_id', 'certification_id', 'actor_id', 'event', 'from_status', 'to_status', 'note', 'metadata'];

    protected function casts(): array { return ['metadata' => 'array']; }

    public function alert(): BelongsTo { return $this->belongsTo(TraceabilityAlert::class, 'alert_id'); }
    public function correctiveAction(): BelongsTo { return $this->belongsTo(CorrectiveAction::class); }
    public function certification(): BelongsTo { return $this->belongsTo(Certification::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
