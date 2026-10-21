<?php

namespace App\Http\Controllers;

use App\Helpers\MenuHelper;
use App\Models\AdminAuditLog;
use App\Models\Certification;
use App\Models\Distribution;
use App\Models\EnvironmentalIndicator;
use App\Models\Farm;
use App\Models\HarvestPlan;
use App\Models\Lot;
use App\Models\Product;
use App\Models\ProductRating;
use App\Models\Store;
use App\Models\TraceabilityAlert;
use App\Models\Transformation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TraceabilityDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->role;
        abort_unless(in_array($role, ['admin', 'producteur', 'transformateur', 'distributeur'], true), 403);

        $stats = $this->statsFor($user, $role);
        $chart = $this->chartFor($user, $role);
        $alerts = $this->alertsFor($user, $role);
        $sections = $this->recentSections($user, $role);
        $adminRecentRatings = $role === 'admin'
            ? ProductRating::query()->with(['consumer:id,name', 'product:id,name'])->latest()->limit(5)->get()
            : collect();
        $categories = $role === 'producteur'
            ? Product::where('user_id', $user->id)->select('category', DB::raw('COUNT(*) as total'))->groupBy('category')->orderByDesc('total')->limit(6)->get()
            : collect();
        $harvestSummary = null;
        if ($role === 'producteur') {
            $harvests = HarvestPlan::where('user_id', $user->id);
            $harvestSummary = [
                'planned' => (clone $harvests)->where('status', 'planned')->whereDate('ends_at', '>=', today())->count(),
                'today' => (clone $harvests)->whereIn('status', ['planned', 'in_progress'])->whereDate('starts_at', '<=', today())->whereDate('ends_at', '>=', today())->count(),
                'week' => (clone $harvests)->whereIn('status', ['planned', 'in_progress'])->whereDate('starts_at', '<=', today()->endOfWeek())->whereDate('ends_at', '>=', today()->startOfWeek())->count(),
                'in_progress' => (clone $harvests)->where('status', 'in_progress')->count(),
                'completed' => (clone $harvests)->where('status', 'completed')->count(),
                'overdue' => (clone $harvests)->whereIn('status', ['planned', 'in_progress'])->whereDate('ends_at', '<', today())->count(),
                'quantity' => (clone $harvests)->whereIn('status', ['planned', 'in_progress'])->whereDate('ends_at', '>=', today())->sum('estimated_quantity'),
                'upcoming' => (clone $harvests)->with(['farm:id,name', 'product:id,name'])->whereIn('status', ['planned', 'in_progress'])->whereDate('ends_at', '>=', today())->orderBy('starts_at')->limit(5)->get(),
            ];
        }
        $quickLinks = collect(\App\Helpers\MenuHelper::getMenuGroups()[0]['items'])
            ->reject(fn ($item) => in_array($item['path'], ['/', '/notifications', '/profile', $user->getDashboardUrl()], true))
            ->values();
        $primary = match ($role) {
            'admin' => ['label' => 'Ouvrir la conformité', 'url' => route('admin.compliance.dashboard')],
            'producteur' => ['label' => 'Consulter les produits', 'url' => route('workspace.resources.index', 'products')],
            'transformateur' => ['label' => 'Consulter les transformations', 'url' => route('workspace.resources.index', 'transformations')],
            default => ['label' => 'Consulter les distributions', 'url' => route('workspace.resources.index', 'distributions')],
        };
        $secondary = match ($role) {
            'admin' => ['label' => 'G&eacute;rer les utilisateurs', 'url' => route('admin.users.index')],
            'producteur' => ['label' => 'Consulter les exploitations', 'url' => route('workspace.resources.index', 'farms')],
            'transformateur' => ['label' => 'Consulter les lots', 'url' => route('workspace.resources.index', 'lots')],
            default => ['label' => 'Consulter les points de vente', 'url' => route('workspace.resources.index', 'stores')],
        };
        $exportUrl = match ($role) {
            'admin' => route('admin.users.export'),
            'producteur' => route('workspace.resources.export', 'products'),
            'transformateur' => route('workspace.resources.export', 'transformations'),
            default => route('workspace.resources.export', 'distributions'),
        };
        $alertsUrl = match ($role) {
            'admin' => route('admin.compliance.dashboard'),
            'producteur' => '/workspace/certifications',
            'transformateur' => '/workspace/transformations',
            default => '/workspace/distributions',
        };

        return view('dashboard.role', compact('role', 'stats', 'chart', 'alerts', 'sections', 'categories', 'quickLinks', 'primary', 'secondary', 'exportUrl', 'alertsUrl', 'harvestSummary', 'adminRecentRatings'));
    }

    private function statsFor(User $user, string $role): array
    {
        if ($role === 'admin') {
            $activeCertifications = Certification::where('status', 'valid')->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))->count();

            return [
                $this->stat('Utilisateurs', User::count(), '/admin/users', 'Tous les comptes', 'mint'),
                $this->stat('Produits', Product::count(), '/workspace/products', 'Tous statuts confondus', 'mint'),
                $this->stat('Avis consommateurs', ProductRating::published()->count(), '/admin/product-ratings', 'Avis publiés à consulter', 'amber'),
                $this->stat('Lots', Lot::count(), '/workspace/lots', 'Lots enregistr&eacute;s', 'blue'),
                $this->stat('Certifications actives', $activeCertifications, '/workspace/certifications?status=valid', 'Validit&eacute; en cours', 'mint'),
                $this->stat('Indicateurs environnementaux', EnvironmentalIndicator::count(), '/workspace/indicators', 'Mesures disponibles', 'blue'),
            ];
        }

        if ($role === 'producteur') {
            $products = Product::where('user_id', $user->id);
            $certificates = $this->ownedCertifications($user);

            return [
                $this->stat('Exploitations', Farm::where('user_id', $user->id)->count(), '/workspace/farms', 'Vos sites de production', 'mint'),
                $this->stat('Produits', (clone $products)->count(), '/workspace/products', 'Votre catalogue', 'blue'),
                $this->stat('Produits en brouillon', (clone $products)->where('status', 'draft')->count(), '/workspace/products?status=draft', 'Non publi&eacute;s', 'amber'),
                $this->stat('Certifications actives', (clone $certificates)->where('status', 'valid')->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))->count(), '/workspace/certifications?status=valid', 'Associ&eacute;es &agrave; vos produits', 'mint'),
                $this->stat('Certifications &agrave; suivre', (clone $certificates)->where(fn ($q) => $q->whereIn('status', ['pending', 'expired'])->orWhere(fn ($valid) => $valid->where('status', 'valid')->whereNotNull('expires_at')->whereDate('expires_at', '<=', today()->addDays(30))))->count(), '/workspace/certifications', 'En attente ou bient&ocirc;t expir&eacute;es', 'amber'),
                $this->stat('Indicateurs environnementaux', $this->ownedIndicators($user)->count(), '/workspace/indicators', 'Li&eacute;s &agrave; vos produits', 'blue'),
            ];
        }

        if ($role === 'transformateur') {
            $transformations = Transformation::where('user_id', $user->id);
            $lots = Lot::whereHas('transformations', fn ($q) => $q->where('user_id', $user->id));

            return [
                $this->stat('Transformations', (clone $transformations)->count(), '/workspace/transformations', 'Op&eacute;rations enregistr&eacute;es', 'mint'),
                $this->stat('Lots g&eacute;r&eacute;s', (clone $lots)->count(), '/workspace/lots', 'Reli&eacute;s &agrave; vos transformations', 'blue'),
                $this->stat('Lots actifs', (clone $lots)->where('status', 'active')->count(), '/workspace/lots?status=active', 'En cours de suivi', 'violet'),
                $this->stat('Lots bloqu&eacute;s', (clone $lots)->where('status', 'blocked')->count(), '/workspace/lots?status=blocked', 'N&eacute;cessitent une action', 'amber'),
                $this->stat('Transformations &agrave; v&eacute;rifier', (clone $transformations)->where('status', 'review')->count(), '/workspace/transformations?status=review', 'Statut de contr&ocirc;le', 'amber'),
                $this->stat('Indicateurs saisis', $this->ownedIndicators($user)->count(), '/workspace/indicators', 'Mesures environnementales', 'blue'),
            ];
        }

        $distributions = Distribution::where('user_id', $user->id);

        return [
            $this->stat('Distributions', (clone $distributions)->count(), '/workspace/distributions', 'Vos mouvements', 'mint'),
            $this->stat('Livraisons planifi&eacute;es', (clone $distributions)->where('status', 'planned')->count(), '/workspace/distributions?status=planned', '&Agrave; exp&eacute;dier', 'blue'),
            $this->stat('Livraisons en transit', (clone $distributions)->where('status', 'in_transit')->count(), '/workspace/distributions?status=in_transit', 'En cours de transport', 'violet'),
            $this->stat('Livraisons en retard', (clone $distributions)->where('status', 'delayed')->count(), '/workspace/distributions?status=delayed', 'Suivi requis', 'amber'),
            $this->stat('Points de vente', Store::where('user_id', $user->id)->count(), '/workspace/stores', 'Vos sites de r&eacute;ception', 'blue'),
            $this->stat('Lots distribu&eacute;s', Lot::whereIn('id', (clone $distributions)->select('lot_id'))->distinct()->count(), '/workspace/lots', 'Li&eacute;s &agrave; vos mouvements', 'violet'),
        ];
    }

    private function chartFor(User $user, string $role): array
    {
        $firstMonth = now()->startOfMonth()->subMonths(5);
        $months = collect(range(0, 5))->map(fn ($offset) => $firstMonth->copy()->addMonths($offset));
        $labels = $months->map(fn ($month) => ['janv.', 'f&eacute;vr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'ao&ucirc;t', 'sept.', 'oct.', 'nov.', 'd&eacute;c.'][(int) $month->format('n') - 1])->all();
        $definitions = match ($role) {
            'admin' => [
                ['Produits', Product::query(), '#367b58'],
                ['Lots', Lot::query(), '#6e60a3'],
                ['Transformations et distributions', Transformation::query(), '#428398'],
            ],
            'producteur' => [
                ['Produits', Product::where('user_id', $user->id), '#367b58'],
                ['Exploitations', Farm::where('user_id', $user->id), '#428398'],
            ],
            'transformateur' => [
                ['Transformations', Transformation::where('user_id', $user->id), '#367b58'],
                ['Lots g&eacute;r&eacute;s', Lot::whereHas('transformations', fn ($q) => $q->where('user_id', $user->id)), '#6e60a3'],
            ],
            default => [
                ['Distributions', Distribution::where('user_id', $user->id), '#367b58'],
                ['Points de vente', Store::where('user_id', $user->id), '#428398'],
            ],
        };

        $series = [];
        foreach ($definitions as [$name, $query, $color]) {
            $series[] = ['name' => $name, 'color' => $color, 'values' => $this->monthlyCounts($query, $firstMonth, $months)];
        }
        if ($role === 'admin') {
            $activityCounts = $this->monthlyCounts(Distribution::query(), $firstMonth, $months);
            $otherActivities = $this->monthlyCounts(Transformation::query(), $firstMonth, $months);
            $series[2]['values'] = array_map(fn ($a, $b) => $a + $b, $activityCounts, $otherActivities);
        }

        return ['title' => $role === 'admin' ? 'Activit&eacute; de la plateforme' : match ($role) {
            'producteur' => 'Activit&eacute; de production',
            'transformateur' => 'Activit&eacute; de transformation',
            default => 'Activit&eacute; de distribution',
        }, 'caption' => 'Enregistrements sur les six derniers mois', 'labels' => $labels, 'series' => $series];
    }

    private function monthlyCounts(Builder $query, $firstMonth, Collection $months): array
    {
        $driver = DB::connection()->getDriverName();
        $expression = match ($driver) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql' => "to_char(created_at, 'YYYY-MM')",
            default => "DATE_FORMAT(created_at, '%Y-%m')",
        };
        $counts = $query->where('created_at', '>=', $firstMonth)
            ->selectRaw("{$expression} as month_key, COUNT(*) as aggregate")
            ->groupByRaw($expression)
            ->pluck('aggregate', 'month_key');

        return $months->map(fn ($month) => (int) $counts->get($month->format('Y-m'), 0))->all();
    }

    private function recentSections(User $user, string $role): array
    {
        if ($role === 'admin') {
            return [];
        }

        if ($role === 'producteur') {
            return [
                $this->section('Exploitations r&eacute;cemment ajout&eacute;es', '/workspace/farms', Farm::where('user_id', $user->id)->latest()->limit(4)->get()->map(fn (Farm $farm) => $this->row($farm->name, trim($farm->location.', '.$farm->country, ', '), 'Cr&eacute;&eacute;e le '.$farm->created_at?->format('d/m/Y'), '/workspace/farms'))->all()),
                $this->section('Produits r&eacute;cemment ajout&eacute;s', '/workspace/products', Product::where('user_id', $user->id)->with('farm')->latest()->limit(4)->get()->map(fn (Product $product) => $this->row($product->name, ($product->category ?: 'Cat&eacute;gorie non renseign&eacute;e').' &middot; '.($product->farm?->name ?: 'Aucune exploitation'), $this->statusLabel($product->status).' &middot; '.$product->created_at?->format('d/m/Y'), '/workspace/products'))->all()),
                $this->section('Certifications et mesures', '/workspace/certifications', $this->producerEvidence($user)),
            ];
        }

        if ($role === 'transformateur') {
            $transformations = Transformation::where('user_id', $user->id)->with('lots.product')->latest()->limit(5)->get()->map(fn (Transformation $item) => $this->row($item->name, $item->reference.' &middot; '.$item->lots->count().' lot(s) li&eacute;(s)', $this->statusLabel($item->status).' &middot; '.$item->created_at?->format('d/m/Y'), '/workspace/transformations'))->all();
            $lots = Lot::whereHas('transformations', fn ($q) => $q->where('user_id', $user->id))->with(['product', 'transformations' => fn ($q) => $q->where('user_id', $user->id)])->latest()->limit(5)->get()->map(fn (Lot $lot) => $this->row($lot->code, $lot->product?->name ?: 'Produit associ&eacute;', $this->statusLabel($lot->status).' &middot; '.$lot->quantity.' '.$lot->unit, '/workspace/lots'))->all();

            return [
                $this->section('Transformations r&eacute;centes', '/workspace/transformations', $transformations),
                $this->section('Lots g&eacute;r&eacute;s', '/workspace/lots', $lots),
            ];
        }

        if ($role === 'distributeur') {
            $distributions = Distribution::where('user_id', $user->id)->with(['lot.product', 'store'])->latest()->limit(5)->get()->map(fn (Distribution $item) => $this->row($item->reference, ($item->lot?->product?->name ?: 'Produit associ&eacute;').' &middot; '.($item->lot?->code ?: 'Lot indisponible'), ($item->store?->name ?: 'Point de vente').' &middot; '.$this->statusLabel($item->status), '/workspace/distributions'))->all();
            $stores = Store::where('user_id', $user->id)->latest()->limit(4)->get()->map(fn (Store $store) => $this->row($store->name, trim($store->city.', '.$store->country, ', '), 'R&eacute;f. '.$store->reference, '/workspace/stores'))->all();
            $linkedProducts = Product::whereIn('id', Distribution::where('user_id', $user->id)->join('lots', 'lots.id', '=', 'distributions.lot_id')->select('lots.product_id'))->with('farm')->distinct()->latest()->limit(5)->get()->map(fn (Product $product) => $this->row($product->name, $product->reference.' &middot; '.($product->farm?->name ?: 'Producteur'), $product->origin ?: ($product->farm?->location ?: 'Origine non renseign&eacute;e'), '/workspace/distributions'))->all();

            return [
                $this->section('Distributions r&eacute;centes', '/workspace/distributions', $distributions),
                $this->section('Points de vente g&eacute;r&eacute;s', '/workspace/stores', $stores),
                $this->section('Produits et lots associ&eacute;s', '/workspace/distributions', $linkedProducts),
            ];
        }

        $activities = collect();
        $this->appendActivities($activities, Product::query()->with('owner')->latest()->limit(5)->get(), 'Produit', fn ($item) => $item->name, fn ($item) => ($item->owner?->name ?: 'Utilisateur').' &middot; '.ucfirst($item->status), '/workspace/products');
        $this->appendActivities($activities, Farm::query()->with('owner')->latest()->limit(5)->get(), 'Exploitation', fn ($item) => $item->name, fn ($item) => ($item->owner?->name ?: 'Utilisateur').' &middot; '.$item->reference, '/workspace/farms');
        $this->appendActivities($activities, User::query()->latest()->limit(5)->get(), 'Compte utilisateur', fn ($item) => $item->name, fn ($item) => ucfirst($item->role).' &middot; '.$item->email, '/admin/users');
        $this->appendActivities($activities, Lot::query()->with('product')->latest()->limit(5)->get(), 'Lot', fn ($item) => $item->code, fn ($item) => ($item->product?->name ?: 'Produit').' &middot; '.ucfirst($item->status), '/workspace/lots');
        $this->appendActivities($activities, Transformation::query()->with('owner')->latest()->limit(5)->get(), 'Transformation', fn ($item) => $item->name, fn ($item) => ($item->owner?->name ?: 'Utilisateur').' &middot; '.$item->reference, '/workspace/transformations');
        $this->appendActivities($activities, Distribution::query()->with(['owner', 'lot.product'])->latest()->limit(5)->get(), 'Distribution', fn ($item) => $item->reference, fn ($item) => ($item->owner?->name ?: 'Utilisateur').' &middot; '.($item->lot?->product?->name ?: 'Produit'), '/workspace/distributions');
        $this->appendActivities($activities, Certification::query()->with('creator')->latest()->limit(5)->get(), 'Certification', fn ($item) => $item->title, fn ($item) => ($item->creator?->name ?: 'Utilisateur').' &middot; '.ucfirst($item->status), '/workspace/certifications');
        $this->appendActivities($activities, EnvironmentalIndicator::query()->with('recorder')->latest()->limit(5)->get(), 'Indicateur', fn ($item) => $item->name, fn ($item) => ($item->recorder?->name ?: 'Utilisateur').' &middot; '.$item->value.' '.$item->unit, '/workspace/indicators');
        $this->appendActivities($activities, \App\Models\ProductRating::query()->with(['consumer:id,name', 'product:id,name'])->latest()->limit(5)->get(), 'Avis consommateur', fn ($item) => ($item->product?->name ?: 'Produit').' · '.$item->rating.'/5', fn ($item) => ($item->consumer?->name ?: ($item->consumer_name ?: 'Visiteur')).($item->comment ? ' · '.mb_strimwidth($item->comment, 0, 90, '…') : ' · Sans commentaire').' · '.($item->status === 'published' ? 'Publié' : 'Masqué'), '/admin/product-ratings');
        AdminAuditLog::latest()->limit(5)->get()->each(function (AdminAuditLog $log) use ($activities) {
            $admin = User::find($log->admin_id);
            $actions = ['user.approved' => 'Compte approuv&eacute;', 'user.activated' => 'Compte r&eacute;activ&eacute;', 'user.suspended' => 'Compte suspendu', 'user.deleted' => 'Compte supprim&eacute;'];
            $activities->push($this->row($actions[$log->action] ?? 'Action administrative', $admin?->name ?: 'Administrateur', 'Administration &middot; '.$log->created_at?->format('d/m/Y H:i'), '/admin/users', $log->created_at));
        });

        return [$this->section('Derni&egrave;res activit&eacute;s', '/admin/alerts', $activities->sortByDesc('date')->take(7)->values()->all())];
    }

    private function appendActivities(Collection $activities, Collection $records, string $kind, callable $title, callable $detail, string $url): void
    {
        foreach ($records as $record) {
            $activities->push($this->row($title($record), $detail($record), $kind.' &middot; '.$record->created_at?->format('d/m/Y H:i'), $url, $record->created_at));
        }
    }

    private function producerEvidence(User $user): array
    {
        $evidence = collect();
        $this->ownedCertifications($user)->with(['product', 'farm'])->latest()->limit(3)->get()->each(function (Certification $item) use ($evidence) {
            $evidence->push($this->row($item->title, ($item->product?->name ?: $item->farm?->name ?: 'Exploitation'), ucfirst($item->status).($item->expires_at ? ' &middot; '.$item->expires_at->format('d/m/Y') : ''), '/workspace/certifications'));
        });
        $this->ownedIndicators($user)->with(['product', 'lot.product'])->latest()->limit(3)->get()->each(function (EnvironmentalIndicator $item) use ($evidence) {
            $evidence->push($this->row($item->name, ($item->product?->name ?: $item->lot?->product?->name ?: 'Produit / lot'), $item->value.' '.$item->unit.' &middot; '.($item->method ? 'M&eacute;thode document&eacute;e' : 'M&eacute;thode manquante'), '/workspace/indicators'));
        });

        return $evidence->sortByDesc('date')->take(5)->values()->all();
    }

    private function alertsFor(User $user, string $role): array
    {
        $query = TraceabilityAlert::whereNotIn('status', ['resolved', 'dismissed'])->latest();
        if ($role !== 'admin') {
            $subjects = match ($role) {
                'producteur' => [
                    Lot::class => Lot::whereHas('product', fn ($q) => $q->where('user_id', $user->id))->select('id'),
                    Certification::class => $this->ownedCertifications($user)->select('id'),
                    EnvironmentalIndicator::class => $this->ownedIndicators($user)->select('id'),
                ],
                'transformateur' => [
                    Transformation::class => Transformation::where('user_id', $user->id)->select('id'),
                    Lot::class => Lot::whereHas('transformations', fn ($q) => $q->where('user_id', $user->id))->select('id'),
                    EnvironmentalIndicator::class => $this->ownedIndicators($user)->select('id'),
                ],
                default => [
                    Distribution::class => Distribution::where('user_id', $user->id)->select('id'),
                    EnvironmentalIndicator::class => $this->ownedIndicators($user)->select('id'),
                ],
            };
            $query->where(function (Builder $where) use ($subjects) {
                foreach ($subjects as $type => $ids) {
                    $where->orWhere(fn (Builder $subject) => $subject->where('subject_type', $type)->whereIn('subject_id', $ids));
                }
            });
        }
        $count = (clone $query)->count();
        $items = $query->limit(4)->get()->map(fn (TraceabilityAlert $alert) => [
            'title' => $alert->title,
            'detail' => $alert->explanation,
            'severity' => $alert->severity,
            'url' => $role === 'admin' ? route('admin.compliance.alerts.show', $alert->id) : $this->alertUrl($alert->subject_type),
        ])->all();

        $extra = collect();
        if ($role === 'admin') {
            $this->ownedCertificationsForAdmin()->where(fn ($q) => $q->where('status', 'expired')->orWhere(fn ($v) => $v->where('status', 'valid')->whereDate('expires_at', '<', today())))->limit(2)->get()->each(fn ($c) => $extra->push($this->alertRow('Certification expir&eacute;e : '.$c->title, $c->certificate_number, 'warning', '/workspace/certifications')));
            EnvironmentalIndicator::where(fn ($q) => $q->whereNull('method')->orWhere('method', ''))->limit(2)->get()->each(fn ($i) => $extra->push($this->alertRow('M&eacute;thode environnementale manquante', $i->name, 'info', '/workspace/indicators')));
            Product::where('status', 'published')->where(fn ($q) => $q->whereNull('farm_id')->orWhereNull('origin')->orWhere('origin', ''))->limit(2)->get()->each(fn ($p) => $extra->push($this->alertRow('Produit publi&eacute; avec une origine incompl&egrave;te', $p->name, 'info', '/workspace/products')));
        } elseif ($role === 'producteur') {
            Product::where('user_id', $user->id)->where(fn ($q) => $q->whereNull('farm_id')->orWhereNull('origin')->orWhere('origin', ''))->limit(3)->get()->each(fn ($p) => $extra->push($this->alertRow('Fiche produit &agrave; compl&eacute;ter', $p->name.' : exploitation ou origine manquante', 'info', '/workspace/products')));
            $this->ownedCertifications($user)->where('status', 'valid')->whereNotNull('expires_at')->whereDate('expires_at', '<=', today()->addDays(30))->limit(3)->get()->each(fn ($c) => $extra->push($this->alertRow('Certification bient&ocirc;t expir&eacute;e', $c->title.' &middot; '.$c->expires_at->format('d/m/Y'), 'warning', '/workspace/certifications')));
        } elseif ($role === 'transformateur') {
            Transformation::where('user_id', $user->id)->with('lots')->latest()->limit(20)->get()->filter(fn ($t) => $t->lots->where('pivot.direction', 'input')->isEmpty() || $t->lots->where('pivot.direction', 'output')->isEmpty())->take(3)->each(fn ($t) => $extra->push($this->alertRow('Tra&ccedil;abilit&eacute; de transformation incompl&egrave;te', $t->reference.' : ajouter un lot entrant et un lot sortant', 'warning', '/workspace/transformations')));
        } else {
            Distribution::where('user_id', $user->id)->whereIn('status', ['planned', 'in_transit'])->where(fn ($q) => $q->whereNull('shipped_at')->orWhereNull('expected_at'))->limit(3)->get()->each(fn ($d) => $extra->push($this->alertRow('Informations de livraison manquantes', $d->reference.' : dates d’exp&eacute;dition ou pr&eacute;vue &agrave; compl&eacute;ter', 'info', '/workspace/distributions')));
        }
        $items = collect($items)->concat($extra)->unique(fn ($item) => $item['title'].'|'.$item['detail'])->take(5)->values()->all();

        return ['count' => max($count, count($items)), 'items' => collect($items)];
    }

    private function ownedCertificationsForAdmin(): Builder
    {
        return Certification::query();
    }

    private function alertRow(string $title, string $detail, string $severity, string $url): array
    {
        return compact('title', 'detail', 'severity', 'url');
    }

    private function alertUrl(?string $subjectType): string
    {
        return match ($subjectType) {
            Lot::class => '/workspace/lots',
            Certification::class => '/workspace/certifications',
            Transformation::class => '/workspace/transformations',
            Distribution::class => '/workspace/distributions',
            EnvironmentalIndicator::class => '/workspace/indicators',
            default => '/notifications',
        };
    }

    private function ownedCertifications(User $user): Builder
    {
        return Certification::query()->where(fn (Builder $query) => $query
            ->whereHas('product', fn ($product) => $product->where('user_id', $user->id))
            ->orWhereHas('farm', fn ($farm) => $farm->where('user_id', $user->id)));
    }

    private function ownedIndicators(User $user): Builder
    {
        return EnvironmentalIndicator::query()->where(fn (Builder $query) => $query
            ->where('recorded_by', $user->id)
            ->orWhereHas('product', fn ($product) => $product->where('user_id', $user->id))
            ->orWhereHas('lot.product', fn ($product) => $product->where('user_id', $user->id)));
    }

    private function stat(string $label, int $value, string $url, string $hint, string $tone): array
    {
        return compact('label', 'value', 'url', 'hint', 'tone');
    }

    private function statusLabel(string $status): string
    {
        return [
            'published' => 'Publi&eacute;', 'draft' => 'Brouillon', 'archived' => 'Archiv&eacute;',
            'active' => 'Actif', 'blocked' => 'Bloqu&eacute;', 'completed' => 'Termin&eacute;',
            'recorded' => 'Enregistr&eacute;e', 'review' => '&Agrave; v&eacute;rifier',
            'planned' => 'Planifi&eacute;e', 'in_transit' => 'En transit', 'delivered' => 'Livr&eacute;e',
            'delayed' => 'En retard', 'cancelled' => 'Annul&eacute;e', 'valid' => 'Valide',
            'expired' => 'Expir&eacute;e', 'pending' => 'En attente', 'rejected' => 'Refus&eacute;e',
        ][$status] ?? ucfirst($status);
    }

    private function section(string $title, string $url, array $items): array
    {
        return compact('title', 'url', 'items');
    }

    private function row(string $title, string $subtitle, string $meta, string $url, $date = null): array
    {
        return compact('title', 'subtitle', 'meta', 'url', 'date');
    }
}
