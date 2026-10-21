<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Farm extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'name', 'reference', 'location', 'latitude', 'longitude', 'country', 'farming_method', 'area_hectares', 'started_at', 'document_name', 'document_mime', 'document_contents', 'description', 'status'];

    protected function casts(): array
    {
        return ['area_hectares' => 'decimal:3', 'started_at' => 'date'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class);
    }

    public function harvestPlans(): HasMany
    {
        return $this->hasMany(HarvestPlan::class);
    }
}
