<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HarvestPlan extends Model
{
    use SoftDeletes;

    public const STATUSES = ['planned', 'in_progress', 'completed', 'cancelled'];

    protected $fillable = [
        'user_id', 'farm_id', 'product_id', 'reference', 'starts_at', 'ends_at',
        'estimated_quantity', 'unit', 'status', 'reminder_days', 'notes',
        'reminder_email_for', 'reminder_email_claimed_at', 'reminder_email_sent_at',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date', 'reminder_email_for' => 'date', 'reminder_email_claimed_at' => 'datetime', 'reminder_email_sent_at' => 'datetime', 'estimated_quantity' => 'decimal:3'];
    }

    protected static function booted(): void
    {
        static::updating(function (HarvestPlan $plan): void {
            if ($plan->isDirty(['starts_at', 'ends_at', 'reminder_days'])) {
                $plan->reminder_email_for = null;
                $plan->reminder_email_claimed_at = null;
                $plan->reminder_email_sent_at = null;
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class)->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(HarvestPlanHistory::class)->latest();
    }

    public function getDisplayStatusAttribute(): string
    {
        if (in_array($this->status, ['planned', 'in_progress'], true) && $this->ends_at?->lt(today())) {
            return 'overdue';
        }

        return $this->status;
    }
}
