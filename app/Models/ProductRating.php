<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRating extends Model
{
    public const STATUSES = ['published', 'hidden'];

    protected $fillable = ['product_id', 'consumer_id', 'consumer_name', 'guest_token', 'rating', 'comment', 'status'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumer_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
