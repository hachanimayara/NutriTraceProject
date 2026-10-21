<?php

namespace App\Mail;

use App\Models\HarvestPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class HarvestPlanReminderMail extends Mailable
{
    use Queueable;

    public function __construct(public HarvestPlan $plan) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Rappel de votre événement — NutriTrace');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.harvest-plan-reminder',
            with: ['calendarUrl' => route('producer.harvest-plans.show', $this->plan)],
        );
    }
}
