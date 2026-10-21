<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HarvestPlanHistory extends Model
{
    protected $fillable = ['harvest_plan_id', 'user_id', 'event', 'changes'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(HarvestPlan::class, 'harvest_plan_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
