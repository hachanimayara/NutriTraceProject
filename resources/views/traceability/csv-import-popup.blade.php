<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#25352e;margin:0;padding:22px;background:#f7faf8}button,a,input{font:inherit}section{background:#fff;border:1px solid #e0e8e2;border-radius:15px;padding:18px;margin:0 0 16px}label{display:block;font-size:14px;font-weight:650;margin-bottom:8px}input[type=file]{display:block;width:100%;margin:10px 0}button,.link{display:inline-block;border:0;border-radius:9px;background:#28684d;color:#fff;padding:10px 15px;font-weight:650;text-decoration:none;cursor:pointer}.link{background:#fff;color:#28684d;border:1px solid #dce3de}.alert{padding:12px;border-radius:10px;background:#fff4df;color:#80580e;margin:12px 0}.err{background:#fff0ef;color:#9c2821}.rows{display:grid;gap:12px}.row{display:grid;grid-template-columns:90px 1fr auto;gap:15px;padding:15px;border-bottom:1px solid #edf1ed}.row>[data-prefill-row]{align-self:center;justify-self:end;width:max-content;max-width:100%;padding:9px 13px;font-size:14px;line-height:1.35;white-space:nowrap}.data{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;font-size:13px;overflow-wrap:anywhere}.muted{color:#718078;font-size:13px}ul{color:#8a5412;font-size:12px}@media(max-width:600px){body{padding:12px}.row{grid-template-columns:1fr}.row>[data-prefill-row]{justify-self:start;white-space:normal}.actions{display:grid;gap:8px}}</style>
<h1 style="font-size:23px;margin:0 0 7px">Importer un fichier CSV · {{ $config['label'] }}</h1>
<p class="muted">Le fichier est analysé et prévisualisé. Aucune donnée n’est enregistrée avant confirmation dans le formulaire existant.</p>
@if(session('status'))<div class="alert" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert err" role="alert">{{ $errors->first() }}</div>@endif
<section>
    <form method="post" action="{{ route('workspace.resources.import.analyze', $resource) }}" enctype="multipart/form-data">
        @csrf
        <label>Fichier CSV (UTF-8, virgule ou point-virgule · 1 Mo maximum)
            <input type="file" name="file" accept=".csv,.txt,text/csv,text/plain" required>
        </label>
        <div class="actions"><button type="submit">Analyser le fichier</button> <a class="link" href="{{ route('workspace.resources.import-template', $resource) }}">Télécharger le modèle</a></div>
    </form>
</section>
@if($preview)
    @if($preview['unknown'])<div class="alert"><strong>Colonnes inconnues ignorées :</strong> {{ implode(', ', $preview['unknown']) }}</div>@endif
    <section><h2 style="font-size:17px;margin:0 0 5px">Aperçu · {{ count($preview['rows']) }} ligne(s)</h2><p class="muted">Choisissez une ligne pour préremplir le formulaire actuel. Les valeurs devront être vérifiées et validées avant enregistrement.</p>
        <div class="rows">
            @foreach($preview['rows'] as $index => $row)
                <article class="row"><div><strong>Ligne {{ $row['line'] }}</strong><div class="muted">{{ $row['status'] }}</div></div><div>
                    @if($row['data'])<dl class="data">@foreach($row['data'] as $field => $value)<div><dt class="muted">{{ $field }}</dt><dd style="margin:2px 0">{{ is_array($value) ? implode(' | ', $value) : $value }}</dd></div>@endforeach</dl>@else<p>Aucune valeur reconnue.</p>@endif
                    @if($row['issues'])<ul>@foreach($row['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul>@endif
                </div><button type="button" data-prefill-row='@json($row['data'])' @disabled(!$row['data'])>Préremplir le formulaire</button></article>
            @endforeach
        </div>
    </section>
@endif
<script>
document.querySelectorAll('[data-prefill-row]').forEach(button=>button.addEventListener('click',()=>{const data=JSON.parse(button.dataset.prefillRow);window.parent.postMessage({type:'nutritrace-csv-prefill',data},window.location.origin)}));
</script>
