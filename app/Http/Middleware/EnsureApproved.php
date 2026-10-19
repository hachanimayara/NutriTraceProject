<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureApproved
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || ! $user->isApproved()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Votre compte n’est pas autorisé à accéder à cet espace.']);
        }
        $sessionVersion = $request->session()->get('account_session_version');
        if ($sessionVersion !== null && (int) $sessionVersion !== (int) $user->session_version) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Votre session a expiré. Veuillez vous reconnecter.']);
        }
        $request->session()->put('account_session_version', (int) $user->session_version);

        return $next($request);
    }
}
