<?php

namespace App\Notifications;

use App\Models\HarvestPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HarvestPlanNotification extends Notification
{
    use Queueable;

    public function __construct(public HarvestPlan $plan, public string $eventKey, public string $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'harvest_plan',
            'event_key' => $this->eventKey,
            'harvest_plan_id' => $this->plan->id,
            'reference' => $this->plan->reference,
            'product' => $this->plan->product?->name,
            'farm' => $this->plan->farm?->name,
            'estimated_quantity' => (string) $this->plan->estimated_quantity,
            'unit' => $this->plan->unit,
            'message' => $this->message,
            'url' => route('producer.harvest-plans.show', $this->plan),
        ];
    }
}
