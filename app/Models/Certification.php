<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certification extends Model
{
    use SoftDeletes;

    protected $fillable = ['created_by', 'farm_id', 'product_id', 'title', 'scheme', 'certificate_number', 'issuer', 'issued_at', 'expires_at', 'document_path', 'status', 'notes'];

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'expires_at' => 'date'];
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function farm(): BelongsTo { return $this->belongsTo(Farm::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function complianceEvents(): HasMany { return $this->hasMany(ComplianceEvent::class)->with('actor')->latest(); }
    public function alerts(): MorphMany { return $this->morphMany(TraceabilityAlert::class, 'subject'); }
}
