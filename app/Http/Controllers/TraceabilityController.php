<?php

namespace App\Http\Controllers;

use App\Models\Certification;
use App\Models\ComplianceEvent;
use App\Models\Distribution;
use App\Models\EnvironmentalIndicator;
use App\Models\Farm;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Store;
use App\Models\Transformation;
use App\Services\TraceabilityCsvImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TraceabilityController extends Controller
{
    private const RESOURCES = [
        'farms' => ['model' => Farm::class, 'label' => 'Exploitations agricoles', 'roles' => ['admin', 'producteur'], 'write_roles' => ['producteur'], 'fields' => ['name', 'reference', 'location', 'latitude', 'longitude', 'country', 'farming_method', 'area_hectares', 'started_at', 'status', 'description']],
        'products' => ['model' => Product::class, 'label' => 'Produits', 'roles' => ['admin', 'producteur'], 'write_roles' => ['producteur'], 'fields' => ['name', 'reference', 'category', 'farm_id', 'origin', 'quantity', 'unit', 'status', 'description']],
        'lots' => ['model' => Lot::class, 'label' => 'Lots', 'roles' => ['admin', 'producteur', 'transformateur', 'distributeur'], 'write_roles' => ['producteur', 'transformateur'], 'fields' => ['product_id', 'code', 'quantity', 'unit', 'status', 'produced_at', 'expires_at', 'notes']],
        'transformations' => ['model' => Transformation::class, 'label' => 'Transformations', 'roles' => ['admin', 'transformateur'], 'write_roles' => ['transformateur'], 'fields' => ['reference', 'name', 'facility', 'performed_at', 'process_description', 'energy_kwh', 'water_liters', 'status', 'notes']],
        'stores' => ['model' => Store::class, 'label' => 'Points de vente', 'roles' => ['admin', 'distributeur'], 'write_roles' => ['distributeur'], 'fields' => ['name', 'reference', 'address', 'city', 'country', 'contact_name', 'phone', 'latitude', 'longitude', 'status']],
        'distributions' => ['model' => Distribution::class, 'label' => 'Distributions', 'roles' => ['admin', 'distributeur'], 'write_roles' => ['distributeur'], 'fields' => ['reference', 'lot_id', 'store_id', 'quantity_shipped', 'quantity_delivered', 'unit', 'shipped_at', 'expected_at', 'delivered_at', 'origin_name', 'origin_latitude', 'origin_longitude', 'status', 'notes']],
        'certifications' => ['model' => Certification::class, 'label' => 'Certifications', 'roles' => ['admin', 'producteur', 'transformateur', 'distributeur'], 'write_roles' => ['admin', 'producteur'], 'fields' => ['title', 'scheme', 'certificate_number', 'issuer', 'farm_id', 'product_id', 'issued_at', 'expires_at', 'status', 'notes']],
        'indicators' => ['model' => EnvironmentalIndicator::class, 'label' => 'Indicateurs environnementaux', 'roles' => ['admin', 'producteur', 'transformateur', 'distributeur'], 'write_roles' => ['admin', 'producteur'], 'fields' => ['stage', 'metric_code', 'name', 'value', 'unit', 'product_id', 'lot_id', 'method', 'measured_at', 'source_notes', 'status', 'validated_by', 'validated_at']],
    ];

    public function index(Request $request, string $resource)
    {
        $config = $this->config($resource);
        $statusOptions = $this->statusOptions($resource);
        $query = $this->scopedQuery($resource, $config);
        $categories = $resource === 'products'
            ? (clone $query)->whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category')
            : collect();
        $stages = $resource === 'indicators'
            ? (clone $query)->whereNotNull('stage')->where('stage', '!=', '')->distinct()->orderBy('stage')->pluck('stage')
            : collect();
        if ($resource === 'products') {
            $query->select(['products.id', 'products.user_id', 'products.farm_id', 'products.name', 'products.reference', 'products.category', 'products.origin', 'products.description', 'products.quantity', 'products.unit', 'products.status', 'products.image_name', 'products.image_mime', 'products.created_at', 'products.updated_at', 'products.deleted_at']);
        }
        if ($request->filled('q')) {
            $this->applyResourceSearch($query, $resource, trim($request->string('q')->toString()));
        }
        if ($request->filled('status') && in_array('status', $config['fields'], true)) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('category') && $resource === 'products') {
            $query->where('category', $request->string('category')->toString());
        }
        if ($request->filled('stage') && $resource === 'indicators') {
            $query->where('stage', $request->string('stage')->toString());
        }

        if (auth()->user()->role === 'producteur' && in_array($resource, ['farms', 'products'], true)) {
            $query->with($resource === 'farms' ? ['owner', 'products'] : ['farm']);
            $records = $query->latest()->paginate(5)->withQueryString();
            $modalRecord = null;
            if ($request->query('modal') === 'edit' && $request->filled('record')) {
                $modalQuery = clone $this->scopedQuery($resource, $config);
                if ($resource === 'products') {
                    $modalQuery->select(['products.id', 'products.user_id', 'products.farm_id', 'products.name', 'products.reference', 'products.category', 'products.origin', 'products.description', 'products.quantity', 'products.unit', 'products.status', 'products.image_name', 'products.image_mime', 'products.created_at', 'products.updated_at', 'products.deleted_at']);
                }
                $modalRecord = $modalQuery
                    ->with($resource === 'farms' ? ['products'] : ['farm'])
                    ->findOrFail($request->integer('record'));
            }

            return view('traceability.producer-index', [
                'resource' => $resource,
                'config' => $config,
                'records' => $records,
                'modalRecord' => $modalRecord,
                'stats' => $this->producerStats($resource),
                'options' => $this->options($resource, $modalRecord),
                'categories' => $categories,
                'stages' => $stages,
                'statusOptions' => $statusOptions,
            ]);
        }

        if (auth()->user()->role === 'transformateur' && in_array($resource, ['lots', 'transformations'], true)) {
            $query->with($resource === 'lots' ? ['product', 'transformations'] : ['lots.product']);
            $records = $query->latest()->paginate(5)->withQueryString();
            $modalRecord = $request->query('modal') === 'edit' && $request->filled('record')
                ? (clone $this->scopedQuery($resource, $config))->with($resource === 'lots' ? ['product', 'transformations'] : ['lots.product'])->findOrFail($request->integer('record'))
                : null;

            return view('traceability.transformer-index', [
                'resource' => $resource,
                'config' => $config,
                'records' => $records,
                'modalRecord' => $modalRecord,
                'stats' => $this->transformerStats($resource, $config),
                'options' => $this->options($resource, $modalRecord),
                'stages' => $stages,
            ]);
        }

        if (auth()->user()->role === 'distributeur' && in_array($resource, ['stores', 'distributions'], true)) {
            $relations = $resource === 'stores' ? ['distributions.lot.product'] : ['lot.product', 'store'];
            $query->with($relations);
            $records = $query->latest()->paginate(5)->withQueryString();
            $modalRecord = $request->query('modal') === 'edit' && $request->filled('record')
                ? (clone $this->scopedQuery($resource, $config))->with($relations)->findOrFail($request->integer('record'))
                : null;

            return view('traceability.distributor-index', [
                'resource' => $resource,
                'config' => $config,
                'records' => $records,
                'modalRecord' => $modalRecord,
                'stats' => $this->distributorStats($resource, $config),
                'options' => $this->options($resource, $modalRecord),
            ]);
        }

        if (auth()->user()->isAdmin() && in_array($resource, ['certifications', 'indicators'], true)) {
            $relations = $resource === 'certifications' ? ['product', 'farm', 'creator'] : ['product', 'lot.product', 'recorder'];
            $query->with($relations);
            $records = $query->latest()->paginate(5)->withQueryString();
            $modalRecord = $request->query('modal') === 'edit' && $request->filled('record')
                ? (clone $this->scopedQuery($resource, $config))->with($relations)->findOrFail($request->integer('record'))
                : null;

            return view('traceability.admin-impact-index', [
                'resource' => $resource,
                'config' => $config,
                'records' => $records,
                'modalRecord' => $modalRecord,
                'stats' => $this->adminImpactStats($resource, $config),
                'options' => $this->options($resource, $modalRecord),
                'stages' => $stages,
            ]);
        }

        $records = $query->latest()->paginate(5)->withQueryString();

        return view('traceability.index', ['resource' => $resource, 'config' => $config, 'records' => $records, 'categories' => $categories, 'stages' => $stages, 'statusOptions' => $statusOptions]);
    }

    public function create(string $resource)
    {
        $config = $this->config($resource);
        if (! auth()->user()->isAdmin()) {
            $this->assertCanWrite($config);
        }

        if (auth()->user()->role === 'producteur' && in_array($resource, ['farms', 'products'], true)) {
            return redirect()->route('workspace.resources.index', ['resource' => $resource, 'modal' => 'create']);
        }

        if (auth()->user()->role === 'transformateur' && in_array($resource, ['lots', 'transformations'], true)) {
            return redirect()->route('workspace.resources.index', ['resource' => $resource, 'modal' => 'create']);
        }

        if (auth()->user()->role === 'distributeur' && in_array($resource, ['stores', 'distributions'], true)) {
            return redirect()->route('workspace.resources.index', ['resource' => $resource, 'modal' => 'create']);
        }

        return view('traceability.form', ['resource' => $resource, 'config' => $config, 'record' => null, 'options' => $this->options($resource)]);
    }

    public function store(Request $request, string $resource)
    {
        $config = $this->config($resource);
        $this->assertCanWrite($config);
        if ($resource === 'indicators' && ! auth()->user()->isAdmin() && $request->input('status') === 'valid') {
            throw ValidationException::withMessages([
                'status' => ['Vous ne pouvez pas valider directement cet indicateur.'],
            ]);
        }
        $data = $this->validated($request, $resource, $config);
        $data = $this->setOwnership($data, $resource);
        if ($resource === 'farms' && $request->hasFile('document')) {
            $file = $request->file('document');
            $data['document_name'] = $file->getClientOriginalName();
            $data['document_mime'] = $file->getMimeType();
            $data['document_contents'] = base64_encode($file->getContent());
        }
        if ($resource === 'products' && $request->hasFile('image')) {
            $image = $request->file('image');
            $data['image_name'] = $image->getClientOriginalName();
            $data['image_mime'] = $image->getMimeType();
            $data['image_contents'] = base64_encode($image->getContent());
        }
        if (in_array($resource, ['lots', 'transformations'], true) && $request->hasFile('document')) {
            $file = $request->file('document');
            $data['document_name'] = $file->getClientOriginalName();
            $data['document_mime'] = $file->getMimeType();
            $data['document_contents'] = base64_encode($file->getContent());
        }
        $associatedProducts = $resource === 'farms' && auth()->user()->role === 'producteur' ? ($data['associated_products'] ?? []) : null;
        unset($data['associated_products']);
        if ($resource === 'certifications' && $request->hasFile('document')) {
            $data['document_path'] = $request->file('document')->store('certifications');
        }

        $record = DB::transaction(function () use ($data, $resource, $config, $request, $associatedProducts) {
            $record = $config['model']::create($data);
            $this->syncTransformationLots($record, $resource, $request);
            if ($associatedProducts !== null) {
                $this->syncFarmProducts($record, $associatedProducts);
            }
            if ($record instanceof Certification) {
                ComplianceEvent::create(['certification_id' => $record->id, 'actor_id' => $request->user()->id, 'event' => 'certification.registered', 'to_status' => $record->status, 'note' => $record->notes, 'metadata' => ['certificate_number' => $record->certificate_number, 'scheme' => $record->scheme, 'expires_at' => $record->expires_at?->toDateString(), 'document_attached' => (bool) $record->document_path]]);
            }

            return $record;
        });

        $redirect = (auth()->user()->role === 'producteur' && in_array($resource, ['farms', 'products'], true))
            || (auth()->user()->role === 'transformateur' && in_array($resource, ['farms', 'products', 'lots', 'transformations'], true))
            || (auth()->user()->role === 'distributeur' && in_array($resource, ['stores', 'distributions'], true))
            || (auth()->user()->isAdmin() && in_array($resource, ['certifications', 'indicators'], true))
            ? redirect()->route('workspace.resources.index', $resource)
            : redirect()->route('workspace.resources.show', [$resource, $record]);

        return $redirect->with('status', 'Enregistrement cree.');
    }

    public function show(Request $request, string $resource, int $record)
    {
        $config = $this->config($resource);
        $item = $this->scopedQuery($resource, $config)->findOrFail($record);
        $item->load($this->relations($resource));
        if ($resource === 'certifications' && auth()->user()->isAdmin()) {
            $item->load('complianceEvents');
        }

        $data = ['resource' => $resource, 'config' => $config, 'record' => $item];

        return $request->boolean('popup')
            ? view('traceability.preview', $data)
            : view('traceability.show', $data);
    }

    public function edit(string $resource, int $record)
    {
        $config = $this->config($resource);
        $this->assertCanWrite($config);
        $item = $this->scopedQuery($resource, $config)->findOrFail($record);
        if ($resource === 'lots' && auth()->user()->role === 'transformateur') {
            abort_unless($item->created_by === auth()->id(), 403);
        }

        if (auth()->user()->role === 'producteur' && in_array($resource, ['farms', 'products'], true)) {
            return redirect()->route('workspace.resources.index', ['resource' => $resource, 'modal' => 'edit', 'record' => $record]);
        }

        if (auth()->user()->role === 'transformateur' && in_array($resource, ['lots', 'transformations'], true)) {
            return redirect()->route('workspace.resources.index', ['resource' => $resource, 'modal' => 'edit', 'record' => $record]);
        }

        if (auth()->user()->role === 'distributeur' && in_array($resource, ['stores', 'distributions'], true)) {
            return redirect()->route('workspace.resources.index', ['resource' => $resource, 'modal' => 'edit', 'record' => $record]);
        }

        if (auth()->user()->isAdmin() && in_array($resource, ['certifications', 'indicators'], true)) {
            return redirect()->route('workspace.resources.index', ['resource' => $resource, 'modal' => 'edit', 'record' => $record]);
        }

        return view('traceability.form', ['resource' => $resource, 'config' => $config, 'record' => $item, 'options' => $this->options($resource, $item)]);
    }

    public function update(Request $request, string $resource, int $record)
    {
        $config = $this->config($resource);
        $this->assertCanWrite($config);
        $item = $this->scopedQuery($resource, $config)->findOrFail($record);
        if ($resource === 'lots' && auth()->user()->role === 'transformateur') {
            abort_unless($item->created_by === auth()->id(), 403);
        }
        $data = $this->validated($request, $resource, $config, $item->getKey());
        if ($resource === 'farms' && $request->hasFile('document')) {
            $file = $request->file('document');
            $data['document_name'] = $file->getClientOriginalName();
            $data['document_mime'] = $file->getMimeType();
            $data['document_contents'] = base64_encode($file->getContent());
        }
        if ($resource === 'products') {
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $data['image_name'] = $image->getClientOriginalName();
                $data['image_mime'] = $image->getMimeType();
                $data['image_contents'] = base64_encode($image->getContent());
            } elseif ($request->boolean('remove_image')) {
                $data['image_name'] = null;
                $data['image_mime'] = null;
                $data['image_contents'] = null;
            }
        }
        if (in_array($resource, ['lots', 'transformations'], true) && $request->hasFile('document')) {
            $file = $request->file('document');
            $data['document_name'] = $file->getClientOriginalName();
            $data['document_mime'] = $file->getMimeType();
            $data['document_contents'] = base64_encode($file->getContent());
        }
        $associatedProducts = $resource === 'farms' && auth()->user()->role === 'producteur' ? ($data['associated_products'] ?? []) : null;
        unset($data['associated_products']);
        $oldDocument = $resource === 'certifications' ? $item->document_path : null;
        $oldCertificationStatus = $resource === 'certifications' ? $item->status : null;
        $oldCertificationExpiry = $resource === 'certifications' ? $item->expires_at?->toDateString() : null;
        if ($resource === 'certifications' && $request->hasFile('document')) {
            $data['document_path'] = $request->file('document')->store('certifications');
        }

        DB::transaction(function () use ($item, $data, $resource, $request, $associatedProducts, $oldCertificationStatus, $oldCertificationExpiry) {
            $item->update($data);
            $this->syncTransformationLots($item, $resource, $request);
            if ($associatedProducts !== null) {
                $this->syncFarmProducts($item, $associatedProducts);
            }
            if ($item instanceof Certification) {
                $renewed = $oldCertificationExpiry !== $item->expires_at?->toDateString() && $item->expires_at;
                $event = $renewed ? 'certification.renewed' : ($oldCertificationStatus !== $item->status ? 'certification.reviewed' : 'certification.updated');
                ComplianceEvent::create(['certification_id' => $item->id, 'actor_id' => $request->user()->id, 'event' => $event, 'from_status' => $oldCertificationStatus, 'to_status' => $item->status, 'note' => $data['notes'] ?? null, 'metadata' => ['certificate_number' => $item->certificate_number, 'old_expires_at' => $oldCertificationExpiry, 'expires_at' => $item->expires_at?->toDateString(), 'document_attached' => (bool) $item->document_path]]);
            }
        });
        if ($oldDocument && isset($data['document_path'])) {
            Storage::disk('local')->delete($oldDocument);
        }

        $redirect = (auth()->user()->role === 'producteur' && in_array($resource, ['farms', 'products'], true))
            || (auth()->user()->role === 'transformateur' && in_array($resource, ['farms', 'products', 'lots', 'transformations'], true))
            || (auth()->user()->role === 'distributeur' && in_array($resource, ['stores', 'distributions'], true))
            || (auth()->user()->isAdmin() && in_array($resource, ['certifications', 'indicators'], true))
            ? redirect()->route('workspace.resources.index', $resource)
            : redirect()->route('workspace.resources.show', [$resource, $item]);

        return $redirect->with('status', 'Modifications enregistrees.');
    }

    public function destroy(string $resource, int $record)
    {
        $config = $this->config($resource);
        $this->assertCanWrite($config);
        $item = $this->scopedQuery($resource, $config)->findOrFail($record);
        if ($resource === 'lots' && auth()->user()->role === 'transformateur') {
            abort_unless($item->created_by === auth()->id(), 403);
        }
        if ($resource === 'certifications' && $item->document_path) {
            Storage::disk('local')->delete($item->document_path);
        }
        $item->delete();

        return redirect()->route('workspace.resources.index', $resource)->with('status', 'Enregistrement supprime.');
    }

    public function certificationDocument(int $record)
    {
        $config = $this->config('certifications');
        $certification = $this->scopedQuery('certifications', $config)->findOrFail($record);
        abort_unless($certification->document_path && Storage::disk('local')->exists($certification->document_path), 404);

        $extension = pathinfo($certification->document_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download($certification->document_path, $certification->certificate_number.'.'.$extension);
    }

    public function farmDocument(int $record)
    {
        $farm = $this->scopedQuery('farms', $this->config('farms'))->findOrFail($record);
        abort_unless($farm->document_contents, 404);
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($farm->document_name ?: 'justificatif'));

        return response(base64_decode($farm->document_contents, true) ?: '', 200, [
            'Content-Type' => $farm->document_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function productImage(int $record)
    {
        $product = Product::query()->whereKey($record)->where('status', 'published')->firstOrFail();
        abort_unless($product->image_contents && in_array($product->image_mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        $contents = base64_decode($product->image_contents, true);
        abort_unless($contents !== false, 404);

        return response($contents, 200, [
            'Content-Type' => $product->image_mime,
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function traceabilityDocument(string $resource, int $record)
    {
        abort_unless(in_array($resource, ['lots', 'transformations'], true), 404);
        $item = $this->scopedQuery($resource, $this->config($resource))->findOrFail($record);
        if ($resource === 'lots' && auth()->user()->role === 'transformateur') {
            abort_unless($item->created_by === auth()->id(), 404);
        }
        abort_unless($item->document_contents, 404);
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($item->document_name ?: 'justificatif'));

        return response(base64_decode($item->document_contents, true) ?: '', 200, [
            'Content-Type' => $item->document_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function export(string $resource)
    {
        abort_unless(in_array($resource, ['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'], true), 404);
        $config = $this->config($resource);
        $records = $this->scopedQuery($resource, $config)
            ->with(match ($resource) {
                'farms' => ['owner', 'products'],
                'products' => ['farm'],
                'lots' => ['product', 'transformations'],
                'transformations' => ['lots.product'],
                'stores' => ['distributions.lot.product'],
                'distributions' => ['lot.product', 'store'],
                'certifications' => ['product', 'farm', 'creator'],
                'indicators' => ['product', 'lot.product', 'recorder'],
            })->latest()->get();
        $columns = match ($resource) {
            'farms' => ['name' => 'Exploitation', 'reference' => 'Reference', 'location' => 'Localisation', 'farming_method' => 'Agriculture', 'status' => 'Statut'],
            'products' => ['name' => 'Produit', 'reference' => 'Reference', 'category' => 'Categorie', 'farm.name' => 'Exploitation', 'status' => 'Statut'],
            'lots' => ['code' => 'Numero de lot', 'product.name' => 'Produit', 'transformations.0.reference' => 'Transformation', 'quantity' => 'Quantite', 'unit' => 'Unite', 'status' => 'Statut', 'produced_at' => 'Date de production'],
            'transformations' => ['reference' => 'Reference', 'name' => 'Operation', 'facility' => 'Site', 'performed_at' => 'Date de transformation', 'status' => 'Statut'],
            'stores' => ['name' => 'Point de vente', 'reference' => 'Reference', 'city' => 'Ville', 'contact_name' => 'Responsable', 'status' => 'Statut'],
            'distributions' => ['reference' => 'Reference', 'lot.code' => 'Lot', 'store.name' => 'Destination', 'shipped_at' => 'Expedition', 'status' => 'Statut'],
            'certifications' => ['title' => 'Certification', 'product.name' => 'Produit', 'issuer' => 'Organisme', 'expires_at' => 'Expiration', 'status' => 'Statut'],
            default => ['name' => 'Indicateur', 'product.name' => 'Produit', 'value' => 'Valeur', 'unit' => 'Unite', 'method' => 'Methode', 'measured_at' => 'Date de mesure'],
        };
        $columns += match ($resource) {
            'farms' => ['associated_products' => 'associated_products'],
            'products' => ['farm_id' => 'farm_id'],
            'lots' => ['product_id' => 'product_id'],
            'transformations' => ['input_lots' => 'input_lots', 'output_lots' => 'output_lots'],
            'distributions' => ['lot_id' => 'lot_id', 'store_id' => 'store_id'],
            'certifications' => ['farm_id' => 'farm_id', 'product_id' => 'product_id'],
            'indicators' => ['product_id' => 'product_id', 'lot_id' => 'lot_id'],
            default => [],
        };

        return response()->streamDownload(function () use ($records, $columns) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_values($columns), ';');
            foreach ($records as $record) {
                $row = [];
                foreach (array_keys($columns) as $field) {
                    $value = match ($field) {
                        'associated_products' => $record->products->pluck('id')->implode('|'),
                        'input_lots' => $record->lots->where('pivot.direction', 'input')->pluck('id')->implode('|'),
                        'output_lots' => $record->lots->where('pivot.direction', 'output')->pluck('id')->implode('|'),
                        default => data_get($record, $field, ''),
                    };
                    $row[] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
                }
                fputcsv($output, $row, ';');
            }
            fclose($output);
        }, $resource.'-nutritrace.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importPage(string $resource)
    {
        $config = $this->config($resource);
        $this->assertCanWrite($config);
        $preview = session('traceability_csv_preview');
        if (($preview['resource'] ?? null) !== $resource) {
            $preview = null;
        }
        if (request()->boolean('popup')) {
            return view('traceability.csv-import-popup', compact('resource', 'config', 'preview'));
        }

        return view('traceability.csv-import', compact('resource', 'config', 'preview'));
    }

    public function analyzeImport(Request $request, string $resource, TraceabilityCsvImportService $importer)
    {
        $config = $this->config($resource);
        $this->assertCanWrite($config);
        $validated = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:1024']]);
        $fields = $config['fields'];
        if ($resource === 'farms') {
            $fields[] = 'associated_products';
        }
        if ($resource === 'transformations') {
            array_push($fields, 'input_lots', 'output_lots');
        }
        $preview = $importer->analyze($validated['file'], $resource, $fields);
        if (! $preview['compatible']) {
            return back()->withErrors(['file' => $preview['error'] ?: 'Ce fichier CSV ne correspond pas à cette gestion.']);
        }
        foreach ($preview['rows'] as &$row) {
            try {
                $this->validated(new Request($row['data']), $resource, $config);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $messages) {
                    array_push($row['issues'], ...$messages);
                }
            }
            $row['issues'] = array_values(array_unique($row['issues']));
            $row['status'] = $row['issues'] ? 'À corriger ou compléter' : 'Valide · à confirmer';
        }
        unset($row);
        session(['traceability_csv_preview' => ['resource' => $resource] + $preview]);

        return redirect()->route('workspace.resources.import', ['resource' => $resource, 'popup' => 1])->with('status', 'Analyse terminée. Aucune donnée n’a été enregistrée.');
    }

    public function useImportRow(Request $request, string $resource)
    {
        $config = $this->config($resource);
        $this->assertCanWrite($config);
        $validated = $request->validate(['row' => ['required', 'integer', 'min:0', 'max:99']]);
        $preview = session('traceability_csv_preview');
        abort_unless(($preview['resource'] ?? null) === $resource && isset($preview['rows'][$validated['row']]), 404);
        $data = $preview['rows'][$validated['row']]['data'];
        session()->forget('traceability_csv_preview');

        return redirect()->route('workspace.resources.index', ['resource' => $resource, 'modal' => 'create'])
            ->withInput($data)->with('status', 'Données chargées dans le formulaire. Vérifiez-les et complétez les champs avant de les enregistrer.');
    }

    public function importTemplate(string $resource, TraceabilityCsvImportService $importer)
    {
        $config = $this->config($resource);
        $this->assertCanWrite($config);
        $fields = $config['fields'];
        $fields = array_values(array_diff($fields, ['validated_by', 'validated_at']));
        if ($resource === 'farms') {
            $fields[] = 'associated_products';
        }
        if ($resource === 'transformations') {
            array_push($fields, 'input_lots', 'output_lots');
        }
        $headers = $importer->templateHeaders($fields);

        return response()->streamDownload(function () use ($headers): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers, ';');
            fclose($output);
        }, $resource.'-modele-nutritrace.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function config(string $resource): array
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404);
        $config = self::RESOURCES[$resource];
        abort_unless(in_array(auth()->user()->role, $config['roles'], true), 403);

        return $config;
    }

    private function assertCanWrite(array $config): void
    {
        abort_unless(in_array(auth()->user()->role, $config['write_roles'], true), 403, 'Consultation seule pour ce module.');
    }

    private function scopedQuery(string $resource, array $config): Builder
    {
        $query = $config['model']::query();
        if ($resource === 'products') {
            $query->select(['products.id', 'products.user_id', 'products.farm_id', 'products.name', 'products.reference', 'products.category', 'products.origin', 'products.description', 'products.quantity', 'products.unit', 'products.status', 'products.image_name', 'products.image_mime', 'products.created_at', 'products.updated_at', 'products.deleted_at']);
        }
        $user = auth()->user();
        if ($user->isAdmin()) {
            return $query;
        }

        if ($resource === 'indicators' && in_array($user->role, ['transformateur', 'distributeur'], true)) {
            return $query->whereHas('recorder', fn (Builder $recorder) => $recorder->where('role', 'admin'));
        }
        if ($resource === 'certifications' && in_array($user->role, ['transformateur', 'distributeur'], true)) {
            return $query->whereHas('creator', fn (Builder $creator) => $creator->where('role', 'admin'));
        }

        return match ($resource) {
            'farms', 'products', 'transformations', 'stores', 'distributions' => $query->where('user_id', $user->id),
            'lots' => match ($user->role) {
                'producteur' => $query->whereHas('product', fn (Builder $q) => $q->where('user_id', $user->id)),
                'transformateur' => $query->whereHas('product', fn (Builder $q) => $q->where('status', 'published')),
                'distributeur' => $query->where('status', 'active')->whereHas('product', fn (Builder $q) => $q->where('status', 'published')),
                default => $query->whereRaw('1 = 0'),
            },
            'certifications' => $query->where(function (Builder $q) use ($user) {
                $q->where('created_by', $user->id)->orWhereHas('farm', fn (Builder $farm) => $farm->where('user_id', $user->id))->orWhereHas('product', fn (Builder $product) => $product->where('user_id', $user->id));
            }),
            'indicators' => $query->where(function (Builder $q) use ($user) {
                $q->where('recorded_by', $user->id)->orWhereHas('product', fn (Builder $product) => $product->where('user_id', $user->id))->orWhereHas('lot.product', fn (Builder $product) => $product->where('user_id', $user->id));
            }),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private function validated(Request $request, string $resource, array $config, ?int $id = null): array
    {
        $indicatorStatusRule = auth()->user()->isAdmin()
            ? ['required', Rule::in(['pending', 'valid', 'rejected'])]
            : ['nullable', 'string', Rule::notIn(['valid'])];
        $unique = fn (string $table, string $column = 'reference') => $id ? Rule::unique($table, $column)->ignore($id) : Rule::unique($table, $column);
        $farmExists = Rule::exists('farms', 'id');
        $productExists = Rule::exists('products', 'id');
        $storeExists = Rule::exists('stores', 'id');
        if (! auth()->user()->isAdmin()) {
            $farmExists->where('user_id', auth()->id());
            if (auth()->user()->role === 'transformateur') {
                $productExists->where('status', 'published');
            } else {
                $productExists->where('user_id', auth()->id());
            }
            $storeExists->where('user_id', auth()->id());
        }
        $rules = match ($resource) {
            'farms' => ['name' => ['required', 'string', 'max:160'], 'reference' => ['required', 'string', 'max:80', $unique('farms')], 'location' => ['required', 'string', 'max:190'], 'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'], 'country' => ['required', 'string', 'max:100'], 'farming_method' => ['nullable', 'string', 'max:120'], 'area_hectares' => ['nullable', 'numeric', 'min:0'], 'started_at' => ['nullable', 'date'], 'description' => ['nullable', 'string', 'max:5000'], 'status' => ['sometimes', Rule::in(['active', 'review'])], 'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], 'associated_products' => ['nullable', 'array'], 'associated_products.*' => ['integer', Rule::exists('products', 'id')->where('user_id', auth()->id())]],
            'products' => ['name' => ['required', 'string', 'max:160'], 'reference' => ['required', 'string', 'max:80', $unique('products')], 'category' => ['required', 'string', 'max:100'], 'farm_id' => ['nullable', $farmExists], 'origin' => ['nullable', 'string', 'max:190'], 'quantity' => ['nullable', 'numeric', 'min:0'], 'unit' => ['required', 'string', 'max:24'], 'status' => ['required', Rule::in(['draft', 'published', 'archived'])], 'description' => ['nullable', 'string', 'max:5000'], 'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'remove_image' => ['nullable', 'boolean']],
            'lots' => ['product_id' => ['required', $productExists], 'code' => ['required', 'string', 'max:80', $unique('lots', 'code')], 'quantity' => ['required', 'numeric', 'gt:0'], 'unit' => ['required', 'string', 'max:24'], 'status' => ['required', Rule::in(['active', 'blocked', 'completed'])], 'produced_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after_or_equal:produced_at'], 'notes' => ['nullable', 'string', 'max:5000'], 'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']],
            'transformations' => ['reference' => ['required', 'string', 'max:80', $unique('transformations')], 'name' => ['required', 'string', 'max:160'], 'facility' => ['nullable', 'string', 'max:190'], 'performed_at' => ['required', 'date'], 'process_description' => ['nullable', 'string', 'max:5000'], 'energy_kwh' => ['nullable', 'numeric', 'min:0'], 'water_liters' => ['nullable', 'numeric', 'min:0'], 'status' => ['required', Rule::in(['recorded', 'review'])], 'notes' => ['nullable', 'string', 'max:5000'], 'input_lots' => ['required', 'array', 'min:1'], 'input_lots.*' => ['required', 'integer', Rule::exists('lots', 'id')->where('status', 'active')], 'output_lots' => ['nullable', 'array'], 'output_lots.*' => ['required', 'integer', Rule::exists('lots', 'id')->where('status', 'active')], 'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']],
            'stores' => ['name' => ['required', 'string', 'max:160'], 'reference' => ['required', 'string', 'max:80', $unique('stores')], 'address' => ['required', 'string', 'max:255'], 'city' => ['nullable', 'string', 'max:120'], 'country' => ['required', 'string', 'max:100'], 'contact_name' => ['nullable', 'string', 'max:160'], 'phone' => ['nullable', 'string', 'max:32'], 'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'], 'status' => ['required', Rule::in(['active', 'inactive'])]],
            'distributions' => ['reference' => ['required', 'string', 'max:80', $unique('distributions')], 'lot_id' => ['required', Rule::exists('lots', 'id')->where('status', 'active')], 'store_id' => ['required', $storeExists], 'quantity_shipped' => ['required', 'numeric', 'gt:0', 'max:99999999999'], 'quantity_delivered' => ['nullable', 'numeric', 'min:0', 'lte:quantity_shipped', 'max:99999999999'], 'unit' => ['required', 'string', 'max:24'], 'shipped_at' => ['nullable', 'date'], 'expected_at' => ['nullable', 'date', Rule::when($request->filled('shipped_at'), ['after_or_equal:shipped_at'])], 'delivered_at' => ['nullable', 'date', Rule::when($request->filled('shipped_at'), ['after_or_equal:shipped_at'])], 'origin_name' => ['nullable', 'string', 'max:190'], 'origin_latitude' => ['nullable', 'required_with:origin_longitude', 'numeric', 'between:-90,90'], 'origin_longitude' => ['nullable', 'required_with:origin_latitude', 'numeric', 'between:-180,180'], 'status' => ['required', Rule::in(['planned', 'in_transit', 'delivered', 'delayed', 'cancelled'])], 'notes' => ['nullable', 'string', 'max:5000']],
            'certifications' => ['title' => ['required', 'string', 'max:160'], 'scheme' => ['required', 'string', 'max:120'], 'certificate_number' => ['required', 'string', 'max:100', $unique('certifications', 'certificate_number')], 'issuer' => ['required', 'string', 'max:160'], 'farm_id' => ['nullable', $farmExists], 'product_id' => ['nullable', $productExists], 'issued_at' => ['nullable', 'date', 'before_or_equal:today'], 'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'], 'status' => ['required', Rule::in(['pending', 'valid', 'expired', 'rejected'])], 'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], 'notes' => ['nullable', 'string', 'max:5000']],
            'indicators' => ['stage' => ['required', 'string', 'max:80'], 'metric_code' => ['required', 'string', 'max:80'], 'name' => ['required', 'string', 'max:160'], 'value' => ['required', 'numeric'], 'unit' => ['required', 'string', 'max:32'], 'product_id' => ['nullable', $productExists], 'lot_id' => ['nullable', Rule::exists('lots', 'id')], 'method' => ['nullable', 'string', 'max:190'], 'measured_at' => ['nullable', 'date'], 'source_notes' => ['nullable', 'string', 'max:5000'], 'status' => $indicatorStatusRule],
        };
        $messages = [
            'status.not_in' => 'Vous ne pouvez pas valider directement un indicateur.',
            'exists' => 'Veuillez sélectionner une option valide.',
            'unique' => 'Cette référence existe déjà.',
            'after_or_equal' => 'Cette date doit être postérieure ou égale à la date d’expédition.',
            'lte' => 'La quantité livrée ne peut pas dépasser la quantité expédiée.',
            'gt' => 'La quantité doit être supérieure à zéro.',
            'mimes' => 'Le fichier doit être au format PDF, JPG ou PNG.',
            'max.file' => 'Le fichier ne peut pas dépasser 5 Mo.',
        ];
        $data = $request->validate($rules, $messages);
        if ($resource === 'farms') {
            $data['status'] = $data['status'] ?? 'active';
        }
        if ($resource === 'certifications' && (bool) ($data['farm_id'] ?? null) === (bool) ($data['product_id'] ?? null)) {
            throw ValidationException::withMessages(['farm_id' => 'Associez la certification a une exploitation ou a un produit, mais pas aux deux.']);
        }
        if ($resource === 'transformations' && array_intersect($data['input_lots'] ?? [], $data['output_lots'] ?? [])) {
            throw ValidationException::withMessages(['output_lots' => 'Un lot ne peut pas etre a la fois entrant et sortant pour la meme transformation.']);
        }
        if ($resource === 'indicators' && empty($data['product_id']) && empty($data['lot_id'])) {
            throw ValidationException::withMessages(['product_id' => 'Associez cet indicateur a un produit ou a un lot.']);
        }
        if ($resource === 'certifications') {
            $data['status'] = 'pending';
        }
        if ($resource === 'indicators') {
            if (! auth()->user()->isAdmin()) {
                $data['status'] = 'pending';
            }
            $data['validated_by'] = $data['status'] === 'pending' ? null : auth()->id();
            $data['validated_at'] = $data['status'] === 'pending' ? null : now();
        }
        if ($resource === 'transformations' && ! auth()->user()->isAdmin()) {
            $lotIds = array_unique(array_merge($data['input_lots'] ?? [], $data['output_lots'] ?? []));
            $validCount = Lot::whereIn('id', $lotIds)->where('status', 'active')->whereHas('product', fn (Builder $q) => $q->where('status', 'published'))->count();
            if ($validCount !== count($lotIds)) {
                throw ValidationException::withMessages(['input_lots' => 'Les lots associés doivent être actifs et liés à un produit publié.']);
            }
        }
        if ($resource === 'distributions') {
            $lot = Lot::whereKey($data['lot_id'])->where('status', 'active')->whereHas('product', fn (Builder $q) => $q->where('status', 'published'))->first();
            if (! $lot) {
                throw ValidationException::withMessages(['lot_id' => 'Choisissez un lot actif d’un produit publié.']);
            }
            $alreadyShippedQuery = Distribution::where('lot_id', $lot->id)->where('status', '!=', 'cancelled');
            if ($id) {
                $alreadyShippedQuery->where('id', '!=', $id);
            }
            $alreadyShipped = $alreadyShippedQuery->sum('quantity_shipped');
            if ((float) $alreadyShipped + (float) $data['quantity_shipped'] > (float) $lot->quantity) {
                throw ValidationException::withMessages(['quantity_shipped' => 'La quantité expédiée dépasse la quantité disponible dans ce lot.']);
            }
        }
        if ($resource === 'indicators' && ! empty($data['lot_id'])) {
            $lotQuery = Lot::whereKey($data['lot_id'])->where('status', 'active');
            if (! auth()->user()->isAdmin()) {
                $lotQuery->whereHas('product', function (Builder $q) {
                    if (auth()->user()->role === 'producteur') {
                        $q->where('user_id', auth()->id());
                    } else {
                        $q->where('status', 'published');
                    }
                });
            }
            if (! $lotQuery->exists()) {
                throw ValidationException::withMessages(['lot_id' => 'Ce lot n’est pas accessible ou n’est plus actif.']);
            }
            if (! empty($data['product_id']) && ! Lot::whereKey($data['lot_id'])->where('product_id', $data['product_id'])->exists()) {
                throw ValidationException::withMessages(['lot_id' => 'Le produit choisi ne correspond pas au lot.']);
            }
        }

        $clean = collect($data)->only($config['fields'])->all();
        if (in_array($resource, ['lots', 'transformations'], true)) {
            foreach (['document_name', 'document_mime', 'document_contents'] as $documentField) {
                if (array_key_exists($documentField, $data)) {
                    $clean[$documentField] = $data[$documentField];
                }
            }
            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $clean['document_name'] = $file->getClientOriginalName();
                $clean['document_mime'] = $file->getMimeType();
                $clean['document_contents'] = base64_encode($file->getContent());
            }
        }
        if ($resource === 'farms' && auth()->user()->role === 'producteur') {
            $clean['associated_products'] = $data['associated_products'] ?? [];
        }

        return $clean;
    }

    private function setOwnership(array $data, string $resource): array
    {
        $userId = auth()->id();
        if (in_array($resource, ['farms', 'products', 'transformations', 'stores', 'distributions'], true)) {
            $data['user_id'] = $userId;
        } elseif ($resource === 'lots') {
            $data['created_by'] = $userId;
            $data['public_id'] = (string) Str::uuid();
        } elseif ($resource === 'certifications') {
            $data['created_by'] = $userId;
        } elseif ($resource === 'indicators') {
            $data['recorded_by'] = $userId;
        }

        return $data;
    }

    private function syncTransformationLots(Model $record, string $resource, Request $request): void
    {
        if ($resource !== 'transformations' || ! $record instanceof Transformation) {
            return;
        }

        $rows = [];
        foreach ($request->input('input_lots', []) as $lotId) {
            $lot = Lot::findOrFail($lotId);
            $rows[$lotId] = ['direction' => 'input', 'quantity' => $lot->quantity, 'unit' => $lot->unit];
        }
        foreach ($request->input('output_lots', []) as $lotId) {
            $lot = Lot::findOrFail($lotId);
            $rows[$lotId] = ['direction' => 'output', 'quantity' => $lot->quantity, 'unit' => $lot->unit];
        }
        $record->lots()->sync($rows);
    }

    private function options(string $resource, $record = null): array
    {
        $user = auth()->user();
        $ownedFarms = collect();
        $ownedProducts = collect();
        $activeLots = collect();
        $ownedStores = collect();

        if (in_array($resource, ['farms', 'products', 'certifications'], true)) {
            $farms = Farm::query();
            if (! $user->isAdmin()) {
                $farms->where('user_id', $user->id);
            }
            $ownedFarms = $farms->orderBy('name')->get(['id', 'name']);
        }
        if (in_array($resource, ['farms', 'lots', 'certifications', 'indicators'], true)) {
            $products = Product::query();
            if (! $user->isAdmin() && $user->role === 'transformateur') {
                $products->where('status', 'published');
            } elseif (! $user->isAdmin()) {
                $products->where('user_id', $user->id);
            }
            $ownedProducts = $products->orderBy('name')->get(['id', 'name', 'reference']);
        }
        if (in_array($resource, ['transformations', 'distributions', 'indicators'], true)) {
            $activeLotsQuery = Lot::where('status', 'active')->with('product:id,name')->orderBy('code');
            if (! $user->isAdmin() && in_array($user->role, ['transformateur', 'distributeur'], true)) {
                $activeLotsQuery->whereHas('product', fn ($q) => $q->where('status', 'published'));
            } elseif (! $user->isAdmin() && $user->role === 'producteur') {
                $activeLotsQuery->whereHas('product', fn ($q) => $q->where('user_id', $user->id));
            }
            $activeLots = $activeLotsQuery->get(['id', 'code', 'product_id', 'quantity', 'unit']);
        }
        if ($resource === 'distributions') {
            $stores = Store::query();
            if (! $user->isAdmin()) {
                $stores->where('user_id', $user->id);
            }
            $ownedStores = $stores->orderBy('name')->get(['id', 'name', 'city']);
        }

        return [
            'farms' => $ownedFarms,
            'products' => $ownedProducts,
            'lots' => $activeLots,
            'stores' => $ownedStores,
            'selected_inputs' => $record instanceof Transformation ? $record->lots()->wherePivot('direction', 'input')->pluck('lots.id')->all() : [],
            'selected_outputs' => $record instanceof Transformation ? $record->lots()->wherePivot('direction', 'output')->pluck('lots.id')->all() : [],
        ];
    }

    private function transformerStats(string $resource, array $config): array
    {
        if ($resource === 'transformations') {
            $transformations = $this->scopedQuery($resource, $config);
            $total = (clone $transformations)->count();
            $needsReview = (clone $transformations)->where('status', 'review')->count();
            $yields = DB::table('transformation_lot')
                ->whereIn('transformation_id', (clone $transformations)->select('id'))
                ->selectRaw('transformation_id, SUM(CASE WHEN direction = ? THEN quantity ELSE 0 END) as input_quantity, SUM(CASE WHEN direction = ? THEN quantity ELSE 0 END) as output_quantity', ['input', 'output'])
                ->groupBy('transformation_id');
            $averageYield = DB::query()->fromSub($yields, 'transformation_yields')
                ->where('input_quantity', '>', 0)->where('output_quantity', '>', 0)
                ->avg(DB::raw('(output_quantity / input_quantity) * 100'));

            return [
                ['value' => $total, 'label' => 'Transformations', 'tone' => 'green'],
                ['value' => $averageYield !== null ? (int) round((float) $averageYield).'%' : '—', 'label' => 'Rendement moyen', 'tone' => 'blue'],
                ['value' => $needsReview, 'label' => 'À vérifier', 'tone' => 'amber'],
            ];
        }

        $lots = $this->scopedQuery($resource, $config);
        $total = (clone $lots)->count();
        $active = (clone $lots)->where('status', 'active')->count();
        $blocked = (clone $lots)->where('status', 'blocked')->count();
        $completeOrigins = (clone $lots)->whereHas('product', fn (Builder $product) => $product->whereNotNull('origin')->where('origin', '!=', '')->whereNotNull('farm_id'))->count();

        return [
            ['value' => $active, 'label' => 'Lots actifs', 'tone' => 'green'],
            ['value' => $total ? (int) round(($completeOrigins / $total) * 100).'%' : '0%', 'label' => 'Origine complète', 'tone' => 'blue'],
            ['value' => $blocked, 'label' => 'À vérifier', 'tone' => 'amber'],
        ];
    }

    private function relations(string $resource): array
    {
        return match ($resource) {
            'products' => ['farm'],
            'lots' => ['product.farm', 'transformations', 'distributions.store'],
            'transformations' => ['lots.product'],
            'distributions' => ['lot.product', 'store'],
            'certifications' => ['farm', 'product'],
            'indicators' => ['product', 'lot.product'],
            default => [],
        };
    }

    private function statusOptions(string $resource): array
    {
        return match ($resource) {
            'farms' => ['active' => 'Active', 'review' => 'À vérifier'],
            'products' => ['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé'],
            'lots' => ['active' => 'Actif', 'blocked' => 'Bloqué', 'completed' => 'Terminé'],
            'transformations' => ['recorded' => 'En cours', 'review' => 'À vérifier'],
            'stores' => ['active' => 'Actif', 'inactive' => 'Inactif'],
            'distributions' => ['planned' => 'Planifiée', 'in_transit' => 'En transit', 'delivered' => 'Livrée', 'delayed' => 'En retard', 'cancelled' => 'Annulée'],
            'certifications' => ['pending' => 'En attente', 'valid' => 'Valide', 'expired' => 'Expirée', 'rejected' => 'Refusée'],
            default => [],
        };
    }

    private function applyResourceSearch(Builder $query, string $resource, string $search): void
    {
        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
        $fields = match ($resource) {
            'farms' => ['name', 'reference', 'location', 'country', 'farming_method', 'description'],
            'products' => ['name', 'reference', 'category', 'origin', 'description'],
            'lots' => ['code', 'notes'],
            'transformations' => ['reference', 'name', 'facility', 'process_description', 'notes'],
            'stores' => ['name', 'reference', 'address', 'city', 'country', 'contact_name', 'phone'],
            'distributions' => ['reference', 'unit', 'notes'],
            'certifications' => ['title', 'scheme', 'certificate_number', 'issuer', 'notes'],
            'indicators' => ['stage', 'metric_code', 'name', 'value', 'unit', 'method', 'source_notes'],
            default => [],
        };

        $query->where(function (Builder $matches) use ($fields, $resource, $term): void {
            foreach ($fields as $field) {
                $matches->orWhere($field, 'like', $term);
            }

            $relatedText = static fn (Builder $related, array $columns) => $related->where(function (Builder $nested) use ($columns, $term): void {
                foreach ($columns as $column) {
                    $nested->orWhere($column, 'like', $term);
                }
            });

            match ($resource) {
                'farms' => $matches->orWhereHas('owner', fn (Builder $owner) => $relatedText($owner, ['name', 'email'])),
                'products' => $matches->orWhereHas('farm', fn (Builder $farm) => $relatedText($farm, ['name', 'reference', 'location'])),
                'lots' => $matches->orWhereHas('product', fn (Builder $product) => $relatedText($product, ['name', 'reference', 'category'])),
                'transformations' => $matches->orWhereHas('lots', fn (Builder $lot) => $lot->where('code', 'like', $term)->orWhereHas('product', fn (Builder $product) => $relatedText($product, ['name', 'reference']))),
                'distributions' => $matches
                    ->orWhereHas('store', fn (Builder $store) => $relatedText($store, ['name', 'reference', 'city', 'address']))
                    ->orWhereHas('lot', fn (Builder $lot) => $lot->where('code', 'like', $term)->orWhereHas('product', fn (Builder $product) => $relatedText($product, ['name', 'reference']))),
                'certifications' => $matches
                    ->orWhereHas('product', fn (Builder $product) => $relatedText($product, ['name', 'reference', 'category']))
                    ->orWhereHas('farm', fn (Builder $farm) => $relatedText($farm, ['name', 'reference', 'location'])),
                'indicators' => $matches
                    ->orWhereHas('product', fn (Builder $product) => $relatedText($product, ['name', 'reference']))
                    ->orWhereHas('lot', fn (Builder $lot) => $lot->where('code', 'like', $term)->orWhereHas('product', fn (Builder $product) => $relatedText($product, ['name', 'reference']))),
                default => null,
            };
        });
    }

    private function distributorStats(string $resource, array $config): array
    {
        if ($resource === 'stores') {
            $stores = $this->scopedQuery($resource, $config);
            $total = (clone $stores)->count();
            $cities = (clone $stores)->whereNotNull('city')->where('city', '!=', '')->distinct()->count('city');
            $storeIds = (clone $stores)->select('id');
            $deliveries = Distribution::whereIn('store_id', $storeIds);
            $totalDistributions = (clone $deliveries)->count();
            $received = (clone $deliveries)->where('status', 'delivered')->count();

            return [
                ['value' => $total, 'label' => 'Points de vente', 'tone' => 'green'],
                ['value' => $cities, 'label' => 'Villes couvertes', 'tone' => 'blue'],
                ['value' => $totalDistributions ? (int) round($received / $totalDistributions * 100).'%' : '0%', 'label' => 'Réceptions validées', 'tone' => 'amber'],
            ];
        }

        $distributions = $this->scopedQuery($resource, $config);

        return [
            ['value' => (clone $distributions)->whereIn('status', ['planned', 'in_transit'])->count(), 'label' => 'Mouvements actifs', 'tone' => 'green'],
            ['value' => (clone $distributions)->where('status', 'in_transit')->count(), 'label' => 'En transit', 'tone' => 'blue'],
            ['value' => (clone $distributions)->where('status', 'delayed')->count(), 'label' => 'Retards', 'tone' => 'amber'],
        ];
    }

    private function adminImpactStats(string $resource, array $config): array
    {
        $records = $this->scopedQuery($resource, $config);
        if ($resource === 'certifications') {
            $total = (clone $records)->count();
            $pending = (clone $records)->where('status', 'pending')->count();
            $expiring = (clone $records)->whereBetween('expires_at', [now()->toDateString(), now()->addDays(90)->toDateString()])->count();

            return [
                ['value' => $total, 'label' => 'Certifications', 'tone' => 'green'],
                ['value' => $pending, 'label' => 'En attente', 'tone' => 'blue'],
                ['value' => $expiring, 'label' => 'Expirent sous 90 jours', 'tone' => 'amber'],
            ];
        }

        $total = (clone $records)->count();
        $documented = (clone $records)->whereNotNull('method')->where('method', '!=', '')->whereNotNull('source_notes')->where('source_notes', '!=', '')->count();
        $missingSources = (clone $records)->where(fn (Builder $query) => $query->whereNull('source_notes')->orWhere('source_notes', ''))->count();

        return [
            ['value' => $total, 'label' => 'Mesures', 'tone' => 'green'],
            ['value' => $total ? (int) round($documented / $total * 100).'%' : '0%', 'label' => 'Données documentées', 'tone' => 'blue'],
            ['value' => $missingSources, 'label' => 'Sources manquantes', 'tone' => 'amber'],
        ];
    }

    private function syncFarmProducts(Farm $farm, array $productIds): void
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        $products = Product::query()->where('user_id', auth()->id())
            ->where(fn ($query) => $query->whereNull('farm_id')->orWhere('farm_id', $farm->id))
            ->whereIn('id', $productIds)->get();
        if ($products->count() !== count($productIds)) {
            throw ValidationException::withMessages(['associated_products' => 'Un ou plusieurs produits ne sont plus disponibles pour cette exploitation.']);
        }

        Product::where('user_id', auth()->id())->where('farm_id', $farm->id)
            ->whereNotIn('id', $productIds ?: [0])->update(['farm_id' => null]);
        if ($productIds) {
            Product::whereIn('id', $productIds)->where('user_id', auth()->id())->update(['farm_id' => $farm->id]);
        }
    }

    private function producerStats(string $resource): array
    {
        $userId = auth()->id();
        if ($resource === 'products') {
            $products = Product::where('user_id', $userId);
            $total = (clone $products)->count();
            $incomplete = (clone $products)->where(fn ($query) => $query->whereNull('farm_id')->orWhereNull('origin')->orWhere('origin', ''))->count();

            return [
                ['value' => $total, 'label' => 'Produits', 'tone' => 'green'],
                ['value' => $incomplete, 'label' => 'À compléter', 'tone' => 'blue'],
                ['value' => $total ? (int) round((($total - $incomplete) / $total) * 100).'%' : '0%', 'label' => 'Traçabilité complète', 'tone' => 'amber'],
            ];
        }

        $farms = Farm::where('user_id', $userId);
        $total = (clone $farms)->count();
        $productCount = Product::where('user_id', $userId)->whereNotNull('farm_id')->count();
        $documentedPractices = (clone $farms)->whereNotNull('farming_method')->where('farming_method', '!=', '')->count();

        return [
            ['value' => $total, 'label' => 'Exploitations', 'tone' => 'green'],
            ['value' => $productCount, 'label' => 'Produits liés', 'tone' => 'blue'],
            ['value' => $total ? (int) round(($documentedPractices / $total) * 100).'%' : '0%', 'label' => 'Pratiques documentées', 'tone' => 'amber'],
        ];
    }
}
