<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Distribution extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'lot_id', 'store_id', 'reference', 'quantity_shipped', 'quantity_delivered', 'unit', 'shipped_at', 'expected_at', 'delivered_at', 'status', 'notes', 'origin_name', 'origin_latitude', 'origin_longitude'];

    protected function casts(): array { return ['quantity_shipped' => 'decimal:3', 'quantity_delivered' => 'decimal:3', 'shipped_at' => 'datetime', 'expected_at' => 'datetime', 'delivered_at' => 'datetime', 'origin_latitude' => 'decimal:7', 'origin_longitude' => 'decimal:7']; }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function lot(): BelongsTo { return $this->belongsTo(Lot::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
}
