<?php

namespace App\Notifications;

use App\Models\TraceabilityAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ComplianceDecisionAssigned extends Notification
{
    use Queueable;

    public function __construct(public TraceabilityAlert $alert) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'compliance_decision_assigned',
            'message' => 'Une décision de conformité vous a été attribuée : '.$this->alert->title,
            'preview' => 'État : '.$this->alert->status.'. Décision : '.$this->alert->decision,
            'alert_id' => $this->alert->id,
            'severity' => $this->alert->severity,
            'due_at' => $this->alert->due_at?->toIso8601String(),
            'url' => route('notifications.index'),
        ];
    }
}
