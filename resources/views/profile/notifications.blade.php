@extends('layouts.admin')

@section('content')
<form method="get" data-list-filters class="mb-4 flex flex-wrap gap-3 rounded-xl border border-gray-200 bg-white p-4">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher une notification..." class="min-w-[220px] flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm">
    <select name="read" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"><option value="">Toutes</option><option value="unread" @selected(request('read')==='unread')>Non lues</option><option value="read" @selected(request('read')==='read')>Lues</option></select>
    <button type="submit" class="rounded-lg bg-green-800 px-4 py-2 text-sm font-semibold text-white">Filtrer</button>
</form>
<h1 class="mb-5 text-3xl font-semibold">Notifications</h1>
<div class="space-y-3">
    @forelse($notifications as $notification)
        <article class="rounded-xl border bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <button type="button" data-notification-trigger data-notification-detail="{{ json_encode($notification->data, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}" data-notification-date="{{ $notification->created_at->format('d/m/Y H:i') }}" data-notification-read="{{ $notification->read_at ? '0' : '1' }}" data-notification-read-url="{{ route('notifications.read', $notification->id) }}" data-notification-delete-url="{{ route('notifications.destroy', $notification->id) }}" class="block w-full text-left">
                <p>{{ ($notification->data['message'] ?? null) === 'Nouvelle demande de compte' && !empty($notification->data['name']) ? 'Nouvelle demande de compte : '.$notification->data['name'] : ($notification->data['message'] ?? 'Notification NutriTrace') }}</p>
                @if(isset($notification->data['preview']))<p class="mt-1 text-sm text-gray-600">{{ $notification->data['preview'] }}</p>@elseif(!empty($notification->data['email']))<p class="mt-1 text-sm text-gray-600">{{ $notification->data['email'] }} · {{ match($notification->data['role'] ?? '') { 'producteur' => 'Producteur', 'transformateur' => 'Transformateur', 'distributeur' => 'Distributeur', default => 'Utilisateur' } }}</p>@endif
                <time class="text-sm text-gray-500">{{ $notification->created_at->diffForHumans() }}</time>
            </button>
            @if(isset($notification->data['harvest_plan_id'])&&auth()->user()->role==='producteur')<a class="ml-3 font-semibold text-green-800" href="{{ $notification->data['url'] ?? route('producer.harvest-plans.show',$notification->data['harvest_plan_id']) }}">Voir la récolte</a>@endif
            @if(isset($notification->data['user_id'])&&auth()->user()->isAdmin())<a class="ml-3 text-green-800" href="{{ route('admin.users.show',$notification->data['user_id']) }}">Examiner le compte</a>@endif
            @if(isset($notification->data['conversation_id']) && in_array(auth()->user()->role, ['transformateur','distributeur'], true))<a class="ml-3 font-semibold text-green-800" href="{{ route('messaging.index', ['conversation' => $notification->data['conversation_id']]) }}">Ouvrir la conversation</a>@endif
            @if(!$notification->read_at)<form class="inline" method="post" action="{{ route('notifications.read',$notification->id) }}">@csrf<button class="ml-3 text-blue-700">Marquer lu</button></form>@else<span class="ml-3 text-xs text-gray-400">Lue</span>@endif
        </article>
    @empty
        <p class="rounded-xl bg-white p-6">Aucune notification.</p>
    @endforelse
</div>
<div class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white"><x-pagination :paginator="$notifications" /></div>
@include('profile.partials.notification-detail-dialog')
@endsection
