<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnvironmentalIndicator extends Model
{
    use SoftDeletes;

    protected $fillable = ['recorded_by', 'product_id', 'lot_id', 'stage', 'metric_code', 'name', 'value', 'unit', 'method', 'measured_at', 'source_notes', 'status', 'validated_by', 'validated_at'];

    protected function casts(): array
    {
        return ['value' => 'decimal:5', 'measured_at' => 'date', 'validated_at' => 'datetime'];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }
}
