<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'name', 'reference', 'address', 'city', 'country', 'contact_name', 'phone', 'status', 'latitude', 'longitude'];

    protected function casts(): array { return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7']; }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function distributions(): HasMany { return $this->hasMany(Distribution::class); }
}
