<?php

namespace App\Console\Commands;

use App\Mail\HarvestPlanReminderMail;
use App\Models\HarvestPlan;
use App\Notifications\HarvestPlanNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendHarvestPlanReminders extends Command
{
    protected $signature = 'nutritrace:harvest-reminders';

    protected $description = 'Envoie les rappels et alertes de récolte aux producteurs';

    public function handle(): int
    {
        HarvestPlan::query()->with(['owner', 'farm', 'product'])
            ->whereIn('status', ['planned', 'in_progress'])
            ->whereNotNull('ends_at')
            ->chunkById(100, function ($plans): void {
                foreach ($plans as $plan) {
                    $daysUntil = (int) today()->diffInDays($plan->starts_at, false);
                    $daysUntilEnd = (int) today()->diffInDays($plan->ends_at, false);
                    $reminderDate = $plan->starts_at->copy()->subDays((int) $plan->reminder_days);

                    if ($plan->starts_at->gte(today()) && $reminderDate->lte(today())) {
                        $this->sendReminderEmail($plan, $reminderDate->toDateString());
                    }

                    if ($daysUntil === 0) {
                        $this->notifyOnce($plan, 'due_today', "La récolte de {$plan->product->name} est prévue aujourd’hui.");
                    } elseif ($daysUntil > 0 && $daysUntil === (int) $plan->reminder_days && $plan->reminder_days > 0) {
                        $this->notifyOnce($plan, 'reminder_'.$daysUntil, "Rappel : récolte de {$plan->product->name} prévue dans {$daysUntil} jour(s).");
                    }
                    if ($daysUntilEnd < 0) {
                        $this->notifyOnce($plan, 'overdue', "La récolte de {$plan->product->name} est en retard depuis ".abs($daysUntilEnd).' jour(s).');
                    }
                }
            });

        $this->info('Rappels de récolte vérifiés.');

        return self::SUCCESS;
    }

    private function notifyOnce(HarvestPlan $plan, string $eventKey, string $message): void
    {
        $alreadySent = $plan->owner->notifications()
            ->where('type', HarvestPlanNotification::class)
            ->where('data->harvest_plan_id', $plan->id)
            ->where('data->event_key', $eventKey)
            ->exists();

        if (! $alreadySent) {
            $plan->owner->notify(new HarvestPlanNotification($plan, $eventKey, $message));
        }
    }

    private function sendReminderEmail(HarvestPlan $plan, string $reminderDate): void
    {
        $claim = DB::transaction(function () use ($plan, $reminderDate): ?HarvestPlan {
            $locked = HarvestPlan::query()->whereKey($plan->id)->lockForUpdate()->first();
            if (! $locked || ! in_array($locked->status, ['planned', 'in_progress'], true) || (int) $locked->reminder_days < 1) {
                return null;
            }

            $currentReminderDate = $locked->starts_at->copy()->subDays((int) $locked->reminder_days)->toDateString();
            if ($locked->starts_at->lt(today()) || $currentReminderDate !== $reminderDate || $currentReminderDate > today()->toDateString()) {
                return null;
            }
            if ($locked->reminder_email_for?->toDateString() === $reminderDate && $locked->reminder_email_sent_at) {
                return null;
            }
            if ($locked->reminder_email_claimed_at?->gt(now()->subMinutes(20))) {
                return null;
            }

            $locked->forceFill(['reminder_email_for' => $reminderDate, 'reminder_email_claimed_at' => now()])->saveQuietly();

            return $locked->load(['owner', 'farm', 'product']);
        });

        if (! $claim) {
            return;
        }

        try {
            if (! $claim->owner?->email) {
                throw new \RuntimeException('Le compte producteur lié à ce rappel ne possède pas d’adresse e-mail.');
            }

            Mail::to($claim->owner->email)->send(new HarvestPlanReminderMail($claim));

            HarvestPlan::query()->whereKey($claim->id)->whereDate('reminder_email_for', $reminderDate)->update([
                'reminder_email_sent_at' => now(),
                'reminder_email_claimed_at' => null,
            ]);
        } catch (Throwable $exception) {
            HarvestPlan::query()->whereKey($claim->id)->whereDate('reminder_email_for', $reminderDate)
                ->update(['reminder_email_claimed_at' => null]);
            $diagnostic = $exception->getMessage();
            foreach ([config('mail.mailers.smtp.username'), config('mail.mailers.smtp.password')] as $secret) {
                if (is_string($secret) && $secret !== '') {
                    $diagnostic = str_replace($secret, '[redacted]', $diagnostic);
                }
            }
            Log::error('Échec de l’envoi du rappel de récolte par e-mail.', [
                'harvest_plan_id' => $claim->id,
                'exception_class' => $exception::class,
                'diagnostic' => Str::limit($diagnostic, 400),
            ]);
            $this->error("L’envoi du rappel pour {$claim->reference} a échoué; il sera retenté.");
        }
    }
}
