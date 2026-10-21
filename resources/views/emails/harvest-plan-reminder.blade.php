<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Rappel de votre événement — NutriTrace</title></head>
<body style="margin:0;background:#f5f8f6;padding:24px;font-family:Arial,sans-serif;color:#173f30">
    <main style="max-width:620px;margin:0 auto;border:1px solid #dce8df;border-radius:16px;background:#fff;padding:28px">
        <p style="margin:0;color:#28724d;font-size:13px;font-weight:bold">NutriTrace · Calendrier agricole</p>
        <h1 style="margin:14px 0 8px;font-size:23px">Rappel de votre événement</h1>
        <p style="margin:0 0 22px;color:#52645a;line-height:1.6">Votre rappel de récolte arrive à la date prévue.</p>
        <section style="border-radius:12px;background:#f5f8f6;padding:18px">
            <h2 style="margin:0 0 12px;font-size:18px">{{ $plan->product?->name ?? 'Événement agricole' }}</h2>
            <p style="margin:7px 0"><strong>Date prévue :</strong> du {{ $plan->starts_at?->format('d/m/Y') }} au {{ $plan->ends_at?->format('d/m/Y') }}</p>
            <p style="margin:7px 0"><strong>Horaire :</strong> non précisé dans le calendrier (événement à la journée).</p>
            @if($plan->farm)<p style="margin:7px 0"><strong>Exploitation :</strong> {{ $plan->farm->name }}</p>@endif
            @if($plan->notes)<p style="margin:14px 0 0;white-space:pre-line"><strong>Description :</strong><br>{{ $plan->notes }}</p>@endif
        </section>
        <p style="margin:24px 0 0"><a href="{{ $calendarUrl }}" style="display:inline-block;border-radius:10px;background:#28724d;padding:12px 18px;color:#fff;text-decoration:none;font-weight:bold">Voir l’événement</a></p>
        <p style="margin:22px 0 0;color:#6b7b71;font-size:12px">Ce message a été envoyé automatiquement par NutriTrace.</p>
    </main>
</body>
</html>
