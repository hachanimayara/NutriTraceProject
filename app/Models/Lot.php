<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lot extends Model
{
    use SoftDeletes;

    protected $fillable = ['product_id', 'created_by', 'code', 'public_id', 'quantity', 'unit', 'status', 'produced_at', 'expires_at', 'notes', 'document_name', 'document_mime', 'document_contents'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'produced_at' => 'date', 'expires_at' => 'date'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transformations(): BelongsToMany
    {
        return $this->belongsToMany(Transformation::class, 'transformation_lot')->withPivot(['direction', 'quantity', 'unit'])->withTimestamps();
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(EnvironmentalIndicator::class);
    }
}
