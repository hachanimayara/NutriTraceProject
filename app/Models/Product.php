<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'farm_id', 'name', 'reference', 'category', 'origin', 'description', 'quantity', 'unit', 'status', 'image_name', 'image_mime', 'image_contents', 'open_food_facts_data', 'open_food_facts_code', 'open_food_facts_source', 'open_food_facts_imported_at'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'open_food_facts_data' => 'array', 'open_food_facts_imported_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->public_id ??= (string) Str::uuid();
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class);
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(EnvironmentalIndicator::class);
    }

    public function harvestPlans(): HasMany
    {
        return $this->hasMany(HarvestPlan::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(ProductRating::class);
    }
}
