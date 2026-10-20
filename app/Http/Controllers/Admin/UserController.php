<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Notifications\AccountApproved;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:producteur,transformateur,distributeur,admin'],
            'status' => ['nullable', 'in:pending,approved,suspended'],
        ]);

        $query = User::query()
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

        $users = (clone $query)->latest()->paginate(15)->withQueryString();
        $statusCounts = User::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $roleCounts = User::query()->selectRaw('role, COUNT(*) as total')->groupBy('role')->pluck('total', 'role');
        $counts = [
            'total' => $statusCounts->sum(),
            'pending' => (int) $statusCounts->get('pending', 0),
            'approved' => (int) $statusCounts->get('approved', 0),
            'suspended' => (int) $statusCounts->get('suspended', 0),
            'admin' => (int) $roleCounts->get('admin', 0),
            'actors' => (int) $roleCounts->only(['producteur', 'transformateur', 'distributeur'])->sum(),
        ];

        return view('admin.users.index', compact('users', 'counts'));
    }

    public function show(User $user)
    {
        $user->load('approver');

        return view('admin.users.show', compact('user'));
    }

    public function export(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:producteur,transformateur,distributeur,admin'],
            'status' => ['nullable', 'in:pending,approved,suspended'],
        ]);
        $users = User::query()
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')->get(['name', 'email', 'phone', 'role', 'status', 'created_at']);

        return response()->streamDownload(function () use ($users) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Nom', 'E-mail', 'Téléphone', 'Rôle', 'Statut', 'Inscription'], ';');
            foreach ($users as $user) {
                fputcsv($output, [$user->name, $user->email, $user->phone, $user->role, $user->status, $user->created_at?->format('Y-m-d H:i:s')], ';');
            }
            fclose($output);
        }, 'nutritrace-utilisateurs.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function approve(Request $request, User $user)
    {
        abort_unless($user->status === 'pending' && $user->role !== 'admin' && ! $user->trashed(), 422);
        DB::transaction(function () use ($request, $user) {
            $user->forceFill(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id])->save();
            AdminAuditLog::create(['admin_id' => $request->user()->id, 'subject_id' => $user->id, 'action' => 'user.approved', 'ip_address' => $request->ip()]);
        });
        try {
            $user->notify(new AccountApproved);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('warning', 'Compte approuvé, mais la notification e-mail a échoué. Vérifiez la messagerie et réessayez.');
        }

        return back()->with('status', 'Le compte a été approuvé.');
    }

    public function suspend(Request $request, User $user)
    {
        abort_if($user->role === 'admin' || $user->is_protected_admin, 422);
        $user->forceFill(['status' => 'suspended', 'session_version' => $user->session_version + 1])->save();
        AdminAuditLog::create(['admin_id' => $request->user()->id, 'subject_id' => $user->id, 'action' => 'user.suspended', 'ip_address' => $request->ip()]);

        return back()->with('status', 'Compte suspendu.');
    }

    public function activate(Request $request, User $user)
    {
        abort_unless($user->status === 'suspended' && $user->role !== 'admin' && ! $user->trashed(), 422);
        DB::transaction(function () use ($request, $user) {
            $user->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
                'session_version' => $user->session_version + 1,
            ])->save();
            AdminAuditLog::create(['admin_id' => $request->user()->id, 'subject_id' => $user->id, 'action' => 'user.activated', 'ip_address' => $request->ip()]);
        });
        try {
            $user->notify(new AccountApproved);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('warning', 'Compte réactivé, mais la notification e-mail a échoué. Vérifiez la configuration e-mail.');
        }

        return back()->with('status', 'Le compte a été réactivé.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id || $user->is_protected_admin || $user->role === 'admin', 422);
        $user->forceFill(['status' => 'deleted', 'session_version' => $user->session_version + 1])->save();
        AdminAuditLog::create(['admin_id' => $request->user()->id, 'subject_id' => $user->id, 'action' => 'user.deleted', 'ip_address' => $request->ip()]);
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'Compte supprimé.');
    }
}
