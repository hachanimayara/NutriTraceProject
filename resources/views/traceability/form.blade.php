@extends('layouts.admin')

@section('content')
@php
    $labels = ['name'=>'Nom','reference'=>'Référence','location'=>'Localisation','country'=>'Pays','farming_method'=>'Mode de culture','area_hectares'=>'Surface (hectares)','description'=>'Description','category'=>'Catégorie','farm_id'=>'Exploitation','origin'=>'Origine','quantity'=>'Quantité','unit'=>'Unité','status'=>'Statut','product_id'=>'Produit','code'=>'Code du lot','produced_at'=>'Date de production','expires_at'=>'Date d’expiration','notes'=>'Notes','facility'=>'Site de transformation','performed_at'=>'Date de transformation','process_description'=>'Description du procédé','energy_kwh'=>'Énergie (kWh)','water_liters'=>'Eau (litres)','address'=>'Adresse','city'=>'Ville','contact_name'=>'Personne à contacter','phone'=>'Téléphone','lot_id'=>'Lot','store_id'=>'Point de vente','quantity_shipped'=>'Quantité expédiée','quantity_delivered'=>'Quantité livrée','shipped_at'=>'Date d’expédition','expected_at'=>'Livraison prévue','delivered_at'=>'Date de livraison','title'=>'Nom de la certification','scheme'=>'Référentiel','certificate_number'=>'Numéro de certificat','issuer'=>'Organisme certificateur','issued_at'=>'Date de délivrance','stage'=>'Étape','metric_code'=>'Code de mesure','value'=>'Valeur','method'=>'Méthode de calcul','measured_at'=>'Date de mesure','source_notes'=>'Source et notes'];
    $required = ['name','reference','location','country','category','unit','status','product_id','code','quantity','performed_at','title','scheme','certificate_number','issuer','stage','metric_code','value','lot_id','store_id','quantity_shipped'];
    $selectOptions = ['status'=>match($resource) { 'products'=>['draft'=>'Brouillon','published'=>'Publié','archived'=>'Archivé'], 'lots'=>['active'=>'Actif','blocked'=>'Bloqué','completed'=>'Terminé'], 'transformations'=>['recorded'=>'Enregistré','review'=>'À vérifier'], 'stores'=>['active'=>'Actif','inactive'=>'Inactif'], 'distributions'=>['planned'=>'Planifié','in_transit'=>'En transit','delivered'=>'Livré','delayed'=>'En retard','cancelled'=>'Annulé'], 'certifications'=>['pending'=>'En attente','valid'=>'Valide','expired'=>'Expiré','rejected'=>'Refusé'], default=>[] }];
    $valueFor = function ($field) use ($record) {
        $value = old($field, data_get($record, $field));
        if ($value instanceof DateTimeInterface) {
            return in_array($field, ['produced_at', 'expires_at', 'issued_at', 'measured_at'], true)
                ? $value->format('Y-m-d')
                : $value->format('Y-m-d\\TH:i');
        }

        return $value;
    };
    $typeFor = fn($field) => str_ends_with($field, '_at') || in_array($field, ['produced_at','expires_at','issued_at','measured_at'], true) ? (in_array($field, ['produced_at','expires_at','issued_at','measured_at'], true) ? 'date' : 'datetime-local') : (in_array($field, ['area_hectares','quantity','quantity_shipped','quantity_delivered','energy_kwh','water_liters','value'], true) ? 'number' : 'text');
    $textarea = ['description','notes','process_description','source_notes'];
@endphp
<div class="mx-auto max-w-4xl">
    <a href="{{ route('workspace.resources.index', $resource) }}" class="text-sm font-semibold text-green-800">← Retour à {{ mb_strtolower($config['label']) }}</a>
    <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-8">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $record ? 'Modifier' : 'Ajouter' }} · {{ $config['label'] }}</h1>
        <p class="mt-2 text-sm text-gray-500">Les champs marqués d’un astérisque sont obligatoires. Les données sont enregistrées dans NutriTrace.</p>
        <form method="post" enctype="multipart/form-data" action="{{ $record ? route('workspace.resources.update', [$resource, $record]) : route('workspace.resources.store', $resource) }}" class="mt-7 space-y-5">
            @csrf @if($record) @method('PUT') @endif
            <div class="grid gap-5 sm:grid-cols-2">
                @foreach($config['fields'] as $field)
                    @if($resource === 'certifications' && $field === 'status' && !auth()->user()->isAdmin())<input type="hidden" name="status" value="pending">@continue @endif
                    @if(in_array($field, $textarea, true))
                        <label class="sm:col-span-2"><span class="mb-2 block text-sm font-medium text-gray-800 dark:text-gray-200">{{ $labels[$field] ?? ucfirst(str_replace('_',' ',$field)) }}</span><textarea name="{{ $field }}" rows="4" maxlength="5000" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-900">{{ $valueFor($field) }}</textarea>@error($field)<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
                    @elseif(in_array($field, ['farm_id','product_id','lot_id','store_id'], true))
                        @php $list = match($field) { 'farm_id' => $options['farms'], 'product_id' => $options['products'], 'lot_id' => $options['lots'], 'store_id' => $options['stores'] }; $idValue = $valueFor($field); @endphp
                        <label><span class="mb-2 block text-sm font-medium text-gray-800 dark:text-gray-200">{{ $labels[$field] ?? $field }} @if(in_array($field, $required, true)) * @endif</span><select name="{{ $field }}" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-900"><option value="">Sélectionner…</option>@foreach($list as $option)<option value="{{ $option->id }}" @selected((string)$idValue===(string)$option->id)>{{ $option->reference ?? $option->code ?? '' }} {{ $option->name ?? $option->title ?? '' }}{{ isset($option->product) ? ' · '.$option->product->name : '' }}</option>@endforeach</select>@error($field)<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
                    @else
                        @php $fieldType = $typeFor($field); @endphp
                        <label><span class="mb-2 block text-sm font-medium text-gray-800 dark:text-gray-200">{{ $labels[$field] ?? ucfirst(str_replace('_',' ',$field)) }} @if(in_array($field, $required, true)) * @endif</span>
                            @if($field === 'status' && $selectOptions['status'])<select name="status" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-900">@foreach($selectOptions['status'] as $value=>$label)<option value="{{ $value }}" @selected($valueFor('status')===$value)>{{ $label }}</option>@endforeach</select>
                            @else<input name="{{ $field }}" type="{{ $fieldType }}" value="{{ $valueFor($field) }}" @if(in_array($field, $required, true)) required @endif @if($fieldType==='number') step="any" min="0" @endif class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-900">@endif
                            @error($field)<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
                    @endif
                @endforeach
                @if($resource === 'certifications')
                    <label class="sm:col-span-2"><span class="mb-2 block text-sm font-medium text-gray-800 dark:text-gray-200">Justificatif (PDF, JPG ou PNG · 5 Mo maximum)</span><input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-900">@if($record?->document_path)<span class="mt-1 block text-xs text-gray-500">Un justificatif est déjà enregistré. Un nouveau fichier le remplacera.</span>@endif @error('document')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
                @endif
                @if($resource === 'products')
                    <label class="sm:col-span-2"><span class="mb-2 block text-sm font-medium text-gray-800 dark:text-gray-200">Photo du produit (JPG, PNG ou WebP · 5 Mo maximum)</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-900">@if($record?->image_name)<span class="mt-1 block text-xs text-gray-500">Image actuelle : {{ $record->image_name }}. Une nouvelle image la remplacera.</span><label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_image" value="1"> Supprimer l’image actuelle</label>@endif @error('image')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
                @endif
                @if($resource === 'transformations')
                    @foreach(['input_lots'=>'Lots entrants','output_lots'=>'Lots sortants'] as $key=>$label)
                        <label><span class="mb-2 block text-sm font-medium text-gray-800 dark:text-gray-200">{{ $label }} @if($key==='input_lots') * @endif</span><select name="{{ $key }}[]" multiple @if($key==='input_lots') required @endif class="min-h-32 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-900">@foreach($options['lots'] as $lot)<option value="{{ $lot->id }}" @selected(in_array($lot->id, old($key, $key==='input_lots' ? $options['selected_inputs'] : $options['selected_outputs'])))>{{ $lot->code }} · {{ $lot->product->name }} ({{ $lot->quantity }} {{ $lot->unit }})</option>@endforeach</select>@error($key)<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
                    @endforeach
                @endif
            </div>
            @if($resource==='certifications')<p class="rounded-xl bg-green-50 p-3 text-sm text-green-900">Associez la certification à une exploitation ou à un produit, mais pas aux deux.</p>@endif
            @if($resource==='indicators')<p class="rounded-xl bg-green-50 p-3 text-sm text-green-900">Indiquez la méthode et la source pour rendre la mesure vérifiable. Un indicateur ne constitue pas à lui seul une empreinte certifiée.</p>@endif
            <div class="flex justify-end gap-3 border-t border-gray-100 pt-5 dark:border-gray-800"><a href="{{ route('workspace.resources.index', $resource) }}" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold">Annuler</a><button class="rounded-xl bg-green-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-900">{{ $record ? 'Enregistrer' : 'Créer' }}</button></div>
        </form>
    </div>
</div>
@endsection
