<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Transformation extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'reference', 'name', 'facility', 'performed_at', 'process_description', 'energy_kwh', 'water_liters', 'notes', 'status', 'document_name', 'document_mime', 'document_contents'];

    protected function casts(): array { return ['performed_at' => 'datetime', 'energy_kwh' => 'decimal:3', 'water_liters' => 'decimal:3']; }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function lots(): BelongsToMany { return $this->belongsToMany(Lot::class, 'transformation_lot')->withPivot(['direction', 'quantity', 'unit'])->withTimestamps(); }
}
