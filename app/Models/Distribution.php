<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Distribution extends Model
{
    use SoftDeletes;

    private ?string $statusBeforeUpdate = null;

    protected $fillable = ['user_id', 'lot_id', 'store_id', 'reference', 'quantity_shipped', 'quantity_delivered', 'unit', 'shipped_at', 'expected_at', 'delivered_at', 'status', 'notes', 'origin_name', 'origin_latitude', 'origin_longitude'];

    protected function casts(): array { return ['quantity_shipped' => 'decimal:3', 'quantity_delivered' => 'decimal:3', 'shipped_at' => 'datetime', 'expected_at' => 'datetime', 'delivered_at' => 'datetime', 'origin_latitude' => 'decimal:7', 'origin_longitude' => 'decimal:7']; }

    protected static function booted(): void
    {
        static::created(function (Distribution $distribution): void {
            $distribution->statusHistory()->create([
                'from_status' => null,
                'to_status' => $distribution->status,
                'changed_by' => Auth::id(),
                'changed_at' => $distribution->created_at ?? now(),
            ]);
        });

        static::updating(function (Distribution $distribution): void {
            if ($distribution->isDirty('status')) {
                $distribution->statusBeforeUpdate = $distribution->getOriginal('status');
            }
        });

        static::updated(function (Distribution $distribution): void {
            if ($distribution->wasChanged('status')) {
                $distribution->statusHistory()->create([
                    'from_status' => $distribution->statusBeforeUpdate ?? null,
                    'to_status' => $distribution->status,
                    'changed_by' => Auth::id(),
                    'changed_at' => now(),
                ]);
            }
        });
    }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function lot(): BelongsTo { return $this->belongsTo(Lot::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function statusHistory(): HasMany { return $this->hasMany(DistributionStatusHistory::class)->orderBy('changed_at')->orderBy('id'); }
}
